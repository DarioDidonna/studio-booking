<?php

namespace App\Console\Commands;

use App\Enums\BookingStatus;
use App\Enums\DepositStatus;
use App\Models\Booking;
use App\Services\Payments\DepositService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Date;

#[Signature('bookings:release-unpaid')]
#[Description('Release the slots of online bookings whose deposit was not paid in time')]
class ReleaseUnpaidBookings extends Command
{
    /**
     * Minutes to wait past the deadline, so a payment made at the last moment
     * has time to be confirmed by its webhook before the slot is freed.
     */
    private const GRACE_MINUTES = 10;

    /**
     * Stripe's checkout.session.expired event normally does this; the command
     * is the safety net for an event that never arrives.
     */
    public function handle(DepositService $deposits): int
    {
        $released = 0;

        Booking::query()
            ->where('status', BookingStatus::Pending)
            ->where('deposit_status', DepositStatus::Unpaid)
            ->where('payment_due_at', '<=', Date::now()->subMinutes(self::GRACE_MINUTES))
            ->chunkById(100, function ($bookings) use ($deposits, &$released): void {
                foreach ($bookings as $booking) {
                    if ($deposits->release($booking)) {
                        $released++;
                    }
                }
            });

        $this->info("Released {$released} unpaid bookings.");

        return self::SUCCESS;
    }
}
