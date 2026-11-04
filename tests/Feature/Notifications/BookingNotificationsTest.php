<?php

namespace Tests\Feature\Notifications;

use App\Models\Booking;
use App\Models\Operator;
use App\Models\Service;
use App\Notifications\BookingConfirmed;
use App\Notifications\BookingReminder;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BookingNotificationsTest extends TestCase
{
    use RefreshDatabase;

    public function test_confirmation_shows_times_on_the_business_clock(): void
    {
        config(['app.business_timezone' => 'Europe/Rome']);
        $booking = Booking::factory()
            ->for(Service::factory()->state(['name' => 'Taglio']))
            ->for(Operator::factory()->state(['name' => 'Giulia Rossi']))
            ->confirmed()
            ->create([
                'starts_at' => CarbonImmutable::parse('2027-03-15 09:00', 'UTC'),
                'ends_at' => CarbonImmutable::parse('2027-03-15 10:00', 'UTC'),
            ]);

        $html = (string) (new BookingConfirmed($booking))->toMail($booking->customer)->render();

        $this->assertStringContainsString('Taglio with Giulia Rossi', $html);
        $this->assertStringContainsString('When: 15/03/2027 10:00', $html);
        $this->assertStringContainsString('You can cancel for free until 14/03/2027 10:00.', $html);
    }

    public function test_reminder_is_not_sent_once_the_booking_is_cancelled(): void
    {
        $booking = Booking::factory()->cancelled()->create();

        $this->assertFalse((new BookingReminder($booking))->shouldSend($booking->customer, 'mail'));
    }

    public function test_reminder_is_sent_for_a_confirmed_booking(): void
    {
        $booking = Booking::factory()->confirmed()->create();

        $this->assertTrue((new BookingReminder($booking))->shouldSend($booking->customer, 'mail'));
    }
}
