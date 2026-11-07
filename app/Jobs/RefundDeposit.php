<?php

namespace App\Jobs;

use App\Enums\DepositStatus;
use App\Models\Booking;
use App\Services\Payments\PaymentGateway;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Refunds a paid deposit. Queued, so a slow or failing Stripe call never
 * blocks a cancellation, and retried with backoff until it succeeds.
 *
 * The idempotency key is tied to the booking: if a retry repeats a refund
 * that Stripe already made, Stripe returns the same refund instead of a new one.
 */
class RefundDeposit implements ShouldQueue
{
    use Queueable;

    public int $tries = 5;

    /**
     * @var list<int>
     */
    public array $backoff = [10, 60, 300, 900];

    public function __construct(public Booking $booking)
    {
        $this->afterCommit();
    }

    public function handle(PaymentGateway $gateway): void
    {
        $booking = $this->booking->fresh();

        if ($booking?->deposit_status !== DepositStatus::Paid || $booking->stripe_payment_intent_id === null) {
            return;
        }

        $gateway->refund($booking->stripe_payment_intent_id, "refund-deposit-{$booking->id}");

        $booking->update(['deposit_status' => DepositStatus::Refunded]);
    }
}
