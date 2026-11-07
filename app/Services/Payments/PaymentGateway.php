<?php

namespace App\Services\Payments;

use App\Models\Booking;
use Carbon\CarbonImmutable;

/**
 * The few payment operations deposits need. Stripe implements it in
 * production; tests swap in a fake, so they never call the network.
 */
interface PaymentGateway
{
    /**
     * Start a hosted checkout for the booking's deposit.
     */
    public function createDepositCheckout(Booking $booking, string $successUrl, string $cancelUrl, CarbonImmutable $expiresAt): DepositCheckout;

    /**
     * Refund a payment in full. The same idempotency key never refunds twice.
     */
    public function refund(string $paymentIntentId, string $idempotencyKey): void;
}
