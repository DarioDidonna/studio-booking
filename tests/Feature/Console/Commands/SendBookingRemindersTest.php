<?php

namespace Tests\Feature\Console\Commands;

use App\Models\Booking;
use App\Notifications\BookingReminder;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class SendBookingRemindersTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(CarbonImmutable::parse('2027-03-14 10:00', 'UTC'));
    }

    public function test_reminds_due_confirmed_bookings_and_marks_them_sent(): void
    {
        Notification::fake();
        $booking = $this->confirmedBooking(dueAt: '2027-03-14 09:00', startsAt: '2027-03-15 09:00');

        $this->artisan('bookings:send-reminders')
            ->expectsOutput('Reminders sent: 1')
            ->assertSuccessful();

        Notification::assertSentTo($booking->customer, BookingReminder::class, fn (BookingReminder $notification): bool => $notification->booking->is($booking));
        $this->assertSame('2027-03-14 10:00:00', $booking->fresh()->reminder_sent_at->toDateTimeString());
    }

    public function test_does_not_remind_the_same_booking_twice(): void
    {
        Notification::fake();
        $booking = $this->confirmedBooking(dueAt: '2027-03-14 09:00', startsAt: '2027-03-15 09:00');

        $this->artisan('bookings:send-reminders')->assertSuccessful();
        $this->travel(15)->minutes();
        $this->artisan('bookings:send-reminders')->assertSuccessful();

        Notification::assertSentToTimes($booking->customer, BookingReminder::class, 1);
    }

    public function test_does_not_remind_bookings_that_are_not_due_yet(): void
    {
        Notification::fake();
        $this->confirmedBooking(dueAt: '2027-03-14 11:00', startsAt: '2027-03-15 11:00');

        $this->artisan('bookings:send-reminders')->assertSuccessful();

        Notification::assertNothingSent();
    }

    public function test_does_not_remind_cancelled_bookings(): void
    {
        Notification::fake();
        Booking::factory()->cancelled()->create([
            'starts_at' => CarbonImmutable::parse('2027-03-15 09:00', 'UTC'),
            'ends_at' => CarbonImmutable::parse('2027-03-15 10:00', 'UTC'),
            'reminder_due_at' => CarbonImmutable::parse('2027-03-14 09:00', 'UTC'),
        ]);

        $this->artisan('bookings:send-reminders')->assertSuccessful();

        Notification::assertNothingSent();
    }

    public function test_does_not_remind_bookings_that_have_already_started(): void
    {
        Notification::fake();
        $this->confirmedBooking(dueAt: '2027-03-13 09:00', startsAt: '2027-03-14 09:00');

        $this->artisan('bookings:send-reminders')->assertSuccessful();

        Notification::assertNothingSent();
    }

    private function confirmedBooking(string $dueAt, string $startsAt): Booking
    {
        $start = CarbonImmutable::parse($startsAt, 'UTC');

        return Booking::factory()->confirmed()->create([
            'starts_at' => $start,
            'ends_at' => $start->addHour(),
            'reminder_due_at' => CarbonImmutable::parse($dueAt, 'UTC'),
        ]);
    }
}
