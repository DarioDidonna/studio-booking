<?php

namespace Tests\Fakes;

use App\Models\Booking;
use App\Services\Payments\DepositCheckout;
use App\Services\Payments\PaymentGateway;
use Carbon\CarbonImmutable;
use Stripe\Exception\ApiConnectionException;

/**
 * Records what the app asks of Stripe, without any network call.
 */
class FakePaymentGateway implements PaymentGateway
{
    public bool $failCheckout = false;

    /**
     * @var list<array{booking_id: int, expires_at: CarbonImmutable}>
     */
    public array $checkouts = [];

    /**
     * Refunds by idempotency key, like Stripe: the same key refunds once.
     *
     * @var array<string, string>
     */
    public array $refunds = [];

    public function createDepositCheckout(Booking $booking, string $successUrl, string $cancelUrl, CarbonImmutable $expiresAt): DepositCheckout
    {
        if ($this->failCheckout) {
            throw new ApiConnectionException('Stripe is unreachable.');
        }

        $this->checkouts[] = ['booking_id' => $booking->id, 'expires_at' => $expiresAt];

        return new DepositCheckout("cs_test_{$booking->id}", "https://checkout.stripe.test/pay/cs_test_{$booking->id}");
    }

    public function refund(string $paymentIntentId, string $idempotencyKey): void
    {
        $this->refunds[$idempotencyKey] = $paymentIntentId;
    }
}
