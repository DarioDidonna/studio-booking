<?php

namespace App\Services;

use App\Enums\BookingStatus;
use App\Models\Booking;
use Illuminate\Support\Str;

/**
 * An iCalendar (RFC 5545) event for a booking, attached to the confirmation
 * email so the customer can add it to their phone's calendar in one tap.
 *
 * The UID stays the same for the life of the booking and SEQUENCE grows with
 * every change, so calendars update a moved appointment instead of adding a copy
 * and remove a cancelled one.
 */
class CalendarInvite
{
    public function forBooking(Booking $booking): string
    {
        $booking->loadMissing(['service', 'operator']);
        $isCancelled = $booking->status === BookingStatus::Cancelled;

        $lines = [
            'BEGIN:VCALENDAR',
            'VERSION:2.0',
            'PRODID:-//'.$this->escape(config('app.name')).'//Bookings//IT',
            'CALSCALE:GREGORIAN',
            $isCancelled ? 'METHOD:CANCEL' : 'METHOD:PUBLISH',
            'BEGIN:VEVENT',
            'UID:booking-'.$booking->id.'@'.(parse_url(config('app.url'), PHP_URL_HOST) ?: 'localhost'),
            'SEQUENCE:'.($booking->updated_at?->getTimestamp() ?? 0),
            'DTSTAMP:'.now()->utc()->format('Ymd\THis\Z'),
            'DTSTART:'.$booking->starts_at->utc()->format('Ymd\THis\Z'),
            'DTEND:'.$booking->ends_at->utc()->format('Ymd\THis\Z'),
            'SUMMARY:'.$this->escape($booking->service->name.' – '.config('app.name')),
            'DESCRIPTION:'.$this->escape(__(':service with :operator', [
                'service' => $booking->service->name,
                'operator' => $booking->operator->name,
            ])),
            $isCancelled ? 'STATUS:CANCELLED' : 'STATUS:CONFIRMED',
            'END:VEVENT',
            'END:VCALENDAR',
        ];

        return implode("\r\n", array_map($this->fold(...), $lines))."\r\n";
    }

    public function filename(Booking $booking): string
    {
        return Str::slug(config('app.name')).'-'.$booking->localStartsAt()->format('Y-m-d-Hi').'.ics';
    }

    /**
     * Commas, semicolons, backslashes and newlines are escaped in text values.
     */
    private function escape(string $text): string
    {
        return str_replace(['\\', ';', ',', "\r\n", "\n"], ['\\\\', '\;', '\,', '\n', '\n'], $text);
    }

    /**
     * Lines longer than 75 octets are folded onto continuation lines starting
     * with a space; the space counts, so those carry 74 octets of content.
     */
    private function fold(string $line): string
    {
        $folded = '';
        $limit = 75;

        while (strlen($line) > $limit) {
            $cut = $limit;

            // Never split a multi-byte UTF-8 character.
            while ($cut > 0 && (ord($line[$cut]) & 0xC0) === 0x80) {
                $cut--;
            }

            $folded .= substr($line, 0, $cut)."\r\n ";
            $line = substr($line, $cut);
            $limit = 74;
        }

        return $folded.$line;
    }
}
