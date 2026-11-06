<?php

namespace Tests\Feature\Notifications;

use App\Models\Booking;
use App\Models\Operator;
use App\Models\Service;
use App\Notifications\BookingCancelled;
use App\Notifications\BookingConfirmed;
use App\Notifications\BookingReminder;
use App\Services\CalendarInvite;
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

        $this->assertStringContainsString('Taglio con Giulia Rossi', $html);
        $this->assertStringContainsString('Quando: 15/03/2027 10:00', $html);
        $this->assertStringContainsString('Puoi annullare gratuitamente fino al 14/03/2027 10:00.', $html);
    }

    public function test_confirmation_attaches_a_calendar_event_in_utc(): void
    {
        config(['app.business_timezone' => 'Europe/Rome', 'app.url' => 'https://barberia.test']);
        $booking = Booking::factory()
            ->for(Service::factory()->state(['name' => 'Taglio, barba']))
            ->for(Operator::factory()->state(['name' => 'Giulia Rossi']))
            ->confirmed()
            ->create([
                'starts_at' => CarbonImmutable::parse('2027-03-15 09:00', 'UTC'),
                'ends_at' => CarbonImmutable::parse('2027-03-15 10:00', 'UTC'),
            ]);

        $attachments = (new BookingConfirmed($booking))->toMail($booking->customer)->rawAttachments;

        $this->assertCount(1, $attachments);
        $this->assertStringEndsWith('.ics', $attachments[0]['name']);
        $this->assertStringStartsWith('text/calendar', $attachments[0]['options']['mime']);
        $ics = $attachments[0]['data'];
        $this->assertStringContainsString("BEGIN:VEVENT\r\n", $ics);
        $this->assertStringContainsString("UID:booking-{$booking->id}@barberia.test\r\n", $ics);
        $this->assertStringContainsString("DTSTART:20270315T090000Z\r\n", $ics);
        $this->assertStringContainsString("DTEND:20270315T100000Z\r\n", $ics);
        $this->assertStringContainsString('SUMMARY:Taglio\\, barba', $ics);
    }

    public function test_moved_booking_keeps_its_calendar_uid_with_a_higher_sequence(): void
    {
        $booking = Booking::factory()->confirmed()->create();
        $calendar = app(CalendarInvite::class);
        preg_match('/SEQUENCE:(\d+)/', $calendar->forBooking($booking), $before);

        $this->travel(1)->minutes();
        $booking->update(['starts_at' => $booking->starts_at->addHour(), 'ends_at' => $booking->ends_at->addHour()]);
        $ics = $calendar->forBooking($booking->fresh());
        preg_match('/SEQUENCE:(\d+)/', $ics, $after);

        $this->assertStringContainsString("UID:booking-{$booking->id}@", $ics);
        $this->assertGreaterThan((int) $before[1], (int) $after[1]);
    }

    public function test_cancellation_by_the_barbershop_explains_why(): void
    {
        $booking = Booking::factory()->cancelled()->create();

        $html = (string) (new BookingCancelled($booking, 'Marco non sarà disponibile in quel giorno.'))->toMail($booking->customer)->render();

        $this->assertStringContainsString('Marco non sarà disponibile in quel giorno.', $html);
        $this->assertStringContainsString(route('bookings.create'), $html);
    }

    public function test_cancellation_attaches_an_event_that_removes_the_appointment_from_the_calendar(): void
    {
        $booking = Booking::factory()->cancelled()->create();

        $ics = (new BookingCancelled($booking))->toMail($booking->customer)->rawAttachments[0]['data'];

        $this->assertStringContainsString("METHOD:CANCEL\r\n", $ics);
        $this->assertStringContainsString("STATUS:CANCELLED\r\n", $ics);
        $this->assertStringContainsString("UID:booking-{$booking->id}@", $ics);
    }

    public function test_long_calendar_lines_are_folded_within_75_octets(): void
    {
        $booking = Booking::factory()
            ->for(Service::factory()->state(['name' => str_repeat('Rasatura tradizionale con panno caldo è sapone ', 4)]))
            ->confirmed()
            ->create();

        $lines = explode("\r\n", rtrim(app(CalendarInvite::class)->forBooking($booking), "\r\n"));

        $this->assertNotEmpty(array_filter($lines, fn (string $line): bool => str_starts_with($line, ' ')));
        foreach ($lines as $line) {
            $this->assertLessThanOrEqual(75, strlen($line));
            $this->assertTrue(mb_check_encoding($line, 'UTF-8'));
        }
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
