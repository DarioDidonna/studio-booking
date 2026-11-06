<?php

namespace App\Console\Commands;

use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Notifications\BookingReminder;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Date;

#[Signature('bookings:send-reminders')]
#[Description('Send the reminder email of every confirmed booking whose reminder is due')]
class SendBookingReminders extends Command
{
    /**
     * Each booking is claimed with a conditional update before its reminder is
     * queued, so overlapping or repeated runs never remind a customer twice.
     */
    public function handle(): int
    {
        $now = Date::now();
        $sent = 0;

        Booking::query()
            ->with('customer')
            ->where('status', BookingStatus::Confirmed)
            ->whereNull('reminder_sent_at')
            ->where('reminder_due_at', '<=', $now)
            ->where('starts_at', '>', $now)
            ->chunkById(100, function ($bookings) use ($now, &$sent): void {
                foreach ($bookings as $booking) {
                    $claimed = Booking::query()
                        ->whereKey($booking->id)
                        ->whereNull('reminder_sent_at')
                        ->update(['reminder_sent_at' => $now]);

                    if ($claimed === 1) {
                        $booking->customer?->notify(new BookingReminder($booking));
                        $sent++;
                    }
                }
            });

        $this->info("Reminders sent: {$sent}");

        return self::SUCCESS;
    }
}
