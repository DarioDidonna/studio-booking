<?php

namespace Tests\Feature\Services;

use App\Enums\BookingStatus;
use App\Enums\Weekday;
use App\Exceptions\BookingCannotBeMovedException;
use App\Exceptions\BookingNotStartedException;
use App\Exceptions\CancellationWindowClosedException;
use App\Exceptions\InvalidBookingTransitionException;
use App\Exceptions\SlotNotAvailableException;
use App\Exceptions\TooManyNoShowsException;
use App\Exceptions\TooManyOpenBookingsException;
use App\Models\Booking;
use App\Models\Operator;
use App\Models\Service;
use App\Models\User;
use App\Notifications\BookingCancelled;
use App\Notifications\BookingConfirmed;
use App\Notifications\BookingRescheduled;
use App\Services\BookingService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * 2027-03-15 is a Monday; 10:00 in Rome is 09:00 UTC on that day.
 */
class BookingServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['app.business_timezone' => 'Europe/Rome']);
        $this->travelTo(CarbonImmutable::parse('2027-03-01 12:00', 'UTC'));
    }

    public function test_books_a_free_slot_as_confirmed_with_the_current_price_and_a_history_entry(): void
    {
        Notification::fake();
        [$operator, $service] = $this->operatorOffering(durationMinutes: 45, priceCents: 3500);
        $customer = User::factory()->create();

        $booking = app(BookingService::class)->book($customer, $operator, $service, $this->mondayAt('10:00'), $customer, 'Prima visita');

        $this->assertDatabaseHas('bookings', [
            'id' => $booking->id,
            'customer_id' => $customer->id,
            'operator_id' => $operator->id,
            'service_id' => $service->id,
            'starts_at' => '2027-03-15 09:00:00',
            'ends_at' => '2027-03-15 09:45:00',
            'status' => BookingStatus::Confirmed->value,
            'price_cents' => 3500,
            'notes' => 'Prima visita',
            'reminder_due_at' => '2027-03-14 09:00:00',
        ]);
        $this->assertDatabaseHas('booking_status_histories', [
            'booking_id' => $booking->id,
            'from_status' => null,
            'to_status' => BookingStatus::Confirmed->value,
            'changed_by' => $customer->id,
        ]);
        Notification::assertSentTo($customer, BookingConfirmed::class, fn (BookingConfirmed $notification): bool => $notification->booking->is($booking));
    }

    public function test_booking_made_less_than_a_day_ahead_gets_no_reminder(): void
    {
        [$operator, $service] = $this->operatorOffering(durationMinutes: 60);
        $customer = User::factory()->create();
        $this->travelTo(CarbonImmutable::parse('2027-03-14 12:00', 'UTC'));

        $booking = app(BookingService::class)->book($customer, $operator, $service, $this->mondayAt('10:00'), $customer);

        $this->assertNull($booking->fresh()->reminder_due_at);
    }

    public function test_rejects_a_slot_that_overlaps_an_existing_booking(): void
    {
        [$operator, $service] = $this->operatorOffering(durationMinutes: 60);
        $bookingService = app(BookingService::class);
        $first = User::factory()->create();
        $second = User::factory()->create();
        $bookingService->book($first, $operator, $service, $this->mondayAt('10:00'), $first);
        Notification::fake();

        try {
            $bookingService->book($second, $operator, $service, $this->mondayAt('10:30'), $second);
            $this->fail('The overlapping booking was accepted.');
        } catch (SlotNotAvailableException) {
            $this->assertDatabaseCount('bookings', 1);
            $this->assertDatabaseMissing('bookings', ['customer_id' => $second->id]);
            Notification::assertNothingSent();
        }
    }

    public function test_rejects_a_start_time_that_is_not_an_offered_slot(): void
    {
        [$operator, $service] = $this->operatorOffering(durationMinutes: 60);
        $customer = User::factory()->create();

        $this->expectException(SlotNotAvailableException::class);

        app(BookingService::class)->book($customer, $operator, $service, $this->mondayAt('07:00'), $customer);
    }

    public function test_cancelled_and_past_bookings_do_not_count_towards_the_limit(): void
    {
        [$operator, $service] = $this->operatorOffering(durationMinutes: 60);
        $customer = User::factory()->create();
        Booking::factory()->count(Booking::MAX_UPCOMING_PER_CUSTOMER - 1)->for($customer, 'customer')->confirmed()->create();
        Booking::factory()->for($customer, 'customer')->cancelled()->create();
        Booking::factory()->for($customer, 'customer')->completed()->create([
            'starts_at' => CarbonImmutable::parse('2027-02-01 09:00', 'UTC'),
            'ends_at' => CarbonImmutable::parse('2027-02-01 10:00', 'UTC'),
        ]);

        app(BookingService::class)->book($customer, $operator, $service, $this->mondayAt('10:00'), $customer);

        $this->assertSame(Booking::MAX_UPCOMING_PER_CUSTOMER, $customer->bookings()->where('status', BookingStatus::Confirmed)->count());
    }

    public function test_customer_cannot_hold_more_than_the_maximum_upcoming_bookings(): void
    {
        [$operator, $service] = $this->operatorOffering(durationMinutes: 60);
        $customer = User::factory()->create();
        Booking::factory()->count(Booking::MAX_UPCOMING_PER_CUSTOMER)->for($customer, 'customer')->confirmed()->create();

        try {
            app(BookingService::class)->book($customer, $operator, $service, $this->mondayAt('10:00'), $customer);
            $this->fail('The booking over the limit was accepted.');
        } catch (TooManyOpenBookingsException) {
            $this->assertDatabaseMissing('bookings', ['operator_id' => $operator->id]);
        }
    }

    public function test_admin_booking_at_the_desk_is_not_limited(): void
    {
        [$operator, $service] = $this->operatorOffering(durationMinutes: 60);
        $customer = User::factory()->create();
        Booking::factory()->count(Booking::MAX_UPCOMING_PER_CUSTOMER)->for($customer, 'customer')->confirmed()->create();

        app(BookingService::class)->book($customer, $operator, $service, $this->mondayAt('10:00'), User::factory()->admin()->create());

        $this->assertDatabaseHas('bookings', ['operator_id' => $operator->id, 'customer_id' => $customer->id]);
    }

    public function test_customer_with_too_many_no_shows_cannot_book_online(): void
    {
        [$operator, $service] = $this->operatorOffering(durationMinutes: 60);
        $customer = User::factory()->create();
        $this->noShowsFor($customer, Booking::NO_SHOW_LIMIT);

        $this->expectException(TooManyNoShowsException::class);

        app(BookingService::class)->book($customer, $operator, $service, $this->mondayAt('10:00'), $customer);
    }

    public function test_no_shows_cleared_by_an_admin_no_longer_count(): void
    {
        [$operator, $service] = $this->operatorOffering(durationMinutes: 60);
        $customer = User::factory()->create();
        $this->noShowsFor($customer, Booking::NO_SHOW_LIMIT);
        $customer->forceFill(['no_shows_cleared_at' => now()])->save();

        $booking = app(BookingService::class)->book($customer, $operator, $service, $this->mondayAt('10:00'), $customer);

        $this->assertSame(BookingStatus::Confirmed, $booking->status);
    }

    public function test_admin_can_book_for_a_customer_blocked_for_no_shows(): void
    {
        [$operator, $service] = $this->operatorOffering(durationMinutes: 60);
        $customer = User::factory()->create();
        $this->noShowsFor($customer, Booking::NO_SHOW_LIMIT);

        app(BookingService::class)->book($customer, $operator, $service, $this->mondayAt('10:00'), User::factory()->admin()->create());

        $this->assertDatabaseHas('bookings', ['customer_id' => $customer->id, 'operator_id' => $operator->id]);
    }

    public function test_first_available_barber_is_the_least_busy_that_day(): void
    {
        [$busy, $service] = $this->operatorOffering(durationMinutes: 60);
        $busy->update(['name' => 'Anna']);
        $free = $this->operatorAlsoOffering($service, 'Bruno');
        $this->confirmedBookingFor($busy, '2027-03-15 14:00');
        $customer = User::factory()->create();

        $booking = app(BookingService::class)->bookWithAnyOperator($customer, $service, $this->mondayAt('10:00'), $customer);

        $this->assertTrue($booking->operator->is($free));
    }

    public function test_first_available_barber_skips_whoever_is_taken_at_that_time(): void
    {
        [$taken, $service] = $this->operatorOffering(durationMinutes: 60);
        $other = $this->operatorAlsoOffering($service, 'Bruno');
        $this->confirmedBookingFor($taken, '2027-03-15 09:00');
        $this->confirmedBookingFor($other, '2027-03-15 15:00');
        $this->confirmedBookingFor($other, '2027-03-15 16:00');
        $customer = User::factory()->create();

        $booking = app(BookingService::class)->bookWithAnyOperator($customer, $service, $this->mondayAt('10:00'), $customer);

        $this->assertTrue($booking->operator->is($other));
    }

    public function test_first_available_barber_fails_when_everybody_is_taken(): void
    {
        [$operator, $service] = $this->operatorOffering(durationMinutes: 60);
        $this->confirmedBookingFor($operator, '2027-03-15 09:00');
        $customer = User::factory()->create();

        $this->expectException(SlotNotAvailableException::class);

        app(BookingService::class)->bookWithAnyOperator($customer, $service, $this->mondayAt('10:00'), $customer);
    }

    public function test_reschedule_moves_the_booking_and_sends_the_new_time(): void
    {
        Notification::fake();
        [$operator, $service] = $this->operatorOffering(durationMinutes: 60);
        $customer = User::factory()->create();
        $booking = app(BookingService::class)->book($customer, $operator, $service, $this->mondayAt('10:00'), $customer);
        $booking->update(['reminder_sent_at' => now()]);

        $moved = app(BookingService::class)->reschedule($booking, $this->mondayAt('10:30'), $customer);

        $this->assertDatabaseHas('bookings', [
            'id' => $booking->id,
            'operator_id' => $operator->id,
            'starts_at' => '2027-03-15 09:30:00',
            'ends_at' => '2027-03-15 10:30:00',
            'reminder_due_at' => '2027-03-14 09:30:00',
            'reminder_sent_at' => null,
        ]);
        Notification::assertSentTo($customer, BookingRescheduled::class, fn (BookingRescheduled $notification): bool => $notification->booking->is($moved));
    }

    public function test_reschedule_rejects_a_taken_time(): void
    {
        [$operator, $service] = $this->operatorOffering(durationMinutes: 60);
        $customer = User::factory()->create();
        $booking = app(BookingService::class)->book($customer, $operator, $service, $this->mondayAt('10:00'), $customer);
        $this->confirmedBookingFor($operator, '2027-03-15 13:00');

        $this->expectException(SlotNotAvailableException::class);

        app(BookingService::class)->reschedule($booking, $this->mondayAt('14:30'), $customer);
    }

    public function test_customer_cannot_move_a_booking_once_the_window_has_closed_but_an_admin_can(): void
    {
        [$operator, $service] = $this->operatorOffering(durationMinutes: 60);
        $customer = User::factory()->create();
        $booking = app(BookingService::class)->book($customer, $operator, $service, $this->mondayAt('10:00'), $customer);
        $this->travelTo(CarbonImmutable::parse('2027-03-14 12:00', 'UTC'));

        try {
            app(BookingService::class)->reschedule($booking, $this->mondayAt('15:00'), $customer);
            $this->fail('The customer moved a booking after the window closed.');
        } catch (BookingCannotBeMovedException) {
        }

        app(BookingService::class)->reschedule($booking, $this->mondayAt('15:00'), User::factory()->admin()->create());

        $this->assertSame('2027-03-15 14:00:00', $booking->fresh()->starts_at->toDateTimeString());
    }

    public function test_cancelled_booking_cannot_be_moved(): void
    {
        [$operator, $service] = $this->operatorOffering(durationMinutes: 60);
        $customer = User::factory()->create();
        $booking = app(BookingService::class)->book($customer, $operator, $service, $this->mondayAt('10:00'), $customer);
        app(BookingService::class)->cancel($booking, $customer);

        $this->expectException(BookingCannotBeMovedException::class);

        app(BookingService::class)->reschedule($booking->fresh(), $this->mondayAt('15:00'), $customer);
    }

    public function test_time_off_cancels_upcoming_bookings_but_not_past_ones(): void
    {
        Notification::fake();
        [$operator] = $this->operatorOffering(durationMinutes: 60);
        $past = $this->confirmedBookingFor($operator, '2027-02-26 09:00');
        $upcoming = $this->confirmedBookingFor($operator, '2027-03-02 09:00');

        $cancelled = app(BookingService::class)->cancelForTimeOff(
            $operator,
            CarbonImmutable::parse('2027-02-25 00:00', 'UTC'),
            CarbonImmutable::parse('2027-03-05 00:00', 'UTC'),
            User::factory()->admin()->create(),
        );

        $this->assertSame(1, $cancelled);
        $this->assertSame(BookingStatus::Confirmed, $past->fresh()->status);
        $this->assertSame(BookingStatus::Cancelled, $upcoming->fresh()->status);
    }

    public function test_no_show_recorded_after_the_admin_cleared_them_counts_again(): void
    {
        $customer = User::factory()->create();
        $this->noShowsFor($customer, Booking::NO_SHOW_LIMIT - 1);
        $customer->forceFill(['no_shows_cleared_at' => now()])->save();
        $this->travel(5)->minutes();
        $this->noShowsFor($customer, 1);

        $this->assertSame(1, $customer->recentNoShowsCount());
    }

    public function test_customer_cancels_for_free_until_the_window_closes(): void
    {
        Notification::fake();
        $booking = $this->confirmedBookingStartingAt('2027-03-15 09:00');
        $this->travelTo(CarbonImmutable::parse('2027-03-14 08:59', 'UTC'));

        app(BookingService::class)->cancel($booking, $booking->customer);

        $this->assertSame(BookingStatus::Cancelled, $booking->fresh()->status);
        $this->assertSame('2027-03-14 08:59:00', $booking->fresh()->cancelled_at->toDateTimeString());
        $this->assertDatabaseHas('booking_status_histories', [
            'booking_id' => $booking->id,
            'from_status' => BookingStatus::Confirmed->value,
            'to_status' => BookingStatus::Cancelled->value,
            'changed_by' => $booking->customer_id,
        ]);
        Notification::assertSentTo($booking->customer, BookingCancelled::class);
    }

    public function test_customer_cannot_cancel_once_the_window_has_closed(): void
    {
        Notification::fake();
        $booking = $this->confirmedBookingStartingAt('2027-03-15 09:00');
        $this->travelTo(CarbonImmutable::parse('2027-03-14 09:00', 'UTC'));

        try {
            app(BookingService::class)->cancel($booking, $booking->customer);
            $this->fail('The late cancellation was accepted.');
        } catch (CancellationWindowClosedException) {
            $this->assertSame(BookingStatus::Confirmed, $booking->fresh()->status);
            $this->assertDatabaseEmpty('booking_status_histories');
            Notification::assertNothingSent();
        }
    }

    public function test_admin_can_cancel_after_the_window_has_closed(): void
    {
        $booking = $this->confirmedBookingStartingAt('2027-03-15 09:00');
        $this->travelTo(CarbonImmutable::parse('2027-03-15 08:00', 'UTC'));

        app(BookingService::class)->cancel($booking, User::factory()->admin()->create());

        $this->assertSame(BookingStatus::Cancelled, $booking->fresh()->status);
    }

    public function test_freed_slot_can_be_booked_again_after_cancellation(): void
    {
        [$operator, $service] = $this->operatorOffering(durationMinutes: 60);
        $bookingService = app(BookingService::class);
        $first = User::factory()->create();
        $second = User::factory()->create();
        $cancelled = $bookingService->book($first, $operator, $service, $this->mondayAt('10:00'), $first);
        $bookingService->cancel($cancelled, $first);

        $booking = $bookingService->book($second, $operator, $service, $this->mondayAt('10:00'), $second);

        $this->assertSame(BookingStatus::Confirmed, $booking->status);
    }

    public function test_marks_a_confirmed_booking_completed_and_records_who_did_it(): void
    {
        $booking = $this->confirmedBookingStartingAt('2027-03-15 09:00');
        $admin = User::factory()->admin()->create();
        $this->travelTo(CarbonImmutable::parse('2027-03-15 10:00', 'UTC'));

        app(BookingService::class)->markCompleted($booking, $admin);

        $this->assertSame(BookingStatus::Completed, $booking->fresh()->status);
        $this->assertDatabaseHas('booking_status_histories', [
            'booking_id' => $booking->id,
            'from_status' => BookingStatus::Confirmed->value,
            'to_status' => BookingStatus::Completed->value,
            'changed_by' => $admin->id,
        ]);
    }

    public function test_rejects_marking_a_no_show_before_the_appointment_starts(): void
    {
        $booking = $this->confirmedBookingStartingAt('2027-03-15 09:00');
        $this->travelTo(CarbonImmutable::parse('2027-03-15 08:59', 'UTC'));

        try {
            app(BookingService::class)->markNoShow($booking, User::factory()->admin()->create());
            $this->fail('A future booking became a no-show.');
        } catch (BookingNotStartedException) {
            $this->assertSame(BookingStatus::Confirmed, $booking->fresh()->status);
            $this->assertDatabaseEmpty('booking_status_histories');
        }
    }

    public function test_rejects_a_status_change_the_lifecycle_does_not_allow(): void
    {
        $booking = Booking::factory()->cancelled()->create();
        $this->travelTo($booking->starts_at->addHour());

        try {
            app(BookingService::class)->markNoShow($booking, User::factory()->admin()->create());
            $this->fail('A cancelled booking became a no-show.');
        } catch (InvalidBookingTransitionException) {
            $this->assertSame(BookingStatus::Cancelled, $booking->fresh()->status);
            $this->assertDatabaseEmpty('booking_status_histories');
        }
    }

    /**
     * An operator who offers one service and works on Mondays from 09:00 to 18:00, Rome time.
     *
     * @return array{Operator, Service}
     */
    private function operatorOffering(int $durationMinutes, int $priceCents = 3000): array
    {
        $service = Service::factory()->create(['duration_minutes' => $durationMinutes, 'price_cents' => $priceCents]);
        $operator = Operator::factory()->hasAttached($service)->create();
        $operator->workingHours()->create(['weekday' => Weekday::Monday, 'start_time' => '09:00:00', 'end_time' => '18:00:00']);

        return [$operator, $service];
    }

    private function operatorAlsoOffering(Service $service, string $name): Operator
    {
        $operator = Operator::factory()->hasAttached($service)->create(['name' => $name]);
        $operator->workingHours()->create(['weekday' => Weekday::Monday, 'start_time' => '09:00:00', 'end_time' => '18:00:00']);

        return $operator;
    }

    private function confirmedBookingFor(Operator $operator, string $utcStart): Booking
    {
        $startsAt = CarbonImmutable::parse($utcStart, 'UTC');

        return Booking::factory()->for($operator)->confirmed()->create(['starts_at' => $startsAt, 'ends_at' => $startsAt->addHour()]);
    }

    private function noShowsFor(User $customer, int $count): void
    {
        Booking::factory()->count($count)->for($customer, 'customer')->noShow()->create([
            'starts_at' => CarbonImmutable::parse('2027-02-01 09:00', 'UTC'),
            'ends_at' => CarbonImmutable::parse('2027-02-01 10:00', 'UTC'),
        ]);
    }

    private function mondayAt(string $localTime): CarbonImmutable
    {
        return CarbonImmutable::parse("2027-03-15 {$localTime}", 'Europe/Rome');
    }

    private function confirmedBookingStartingAt(string $utcStart): Booking
    {
        $startsAt = CarbonImmutable::parse($utcStart, 'UTC');

        return Booking::factory()->confirmed()->create([
            'starts_at' => $startsAt,
            'ends_at' => $startsAt->addHour(),
        ]);
    }
}
