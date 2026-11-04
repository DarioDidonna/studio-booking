<?php

namespace Tests\Feature\Services;

use App\Enums\BookingStatus;
use App\Enums\Weekday;
use App\Exceptions\BookingNotStartedException;
use App\Exceptions\CancellationWindowClosedException;
use App\Exceptions\InvalidBookingTransitionException;
use App\Exceptions\SlotNotAvailableException;
use App\Models\Booking;
use App\Models\Operator;
use App\Models\Service;
use App\Models\User;
use App\Notifications\BookingCancelled;
use App\Notifications\BookingConfirmed;
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
