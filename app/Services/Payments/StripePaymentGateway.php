<?php

namespace App\Services\Payments;

use App\Models\Booking;
use Carbon\CarbonImmutable;
use Stripe\StripeClient;

class StripePaymentGateway implements PaymentGateway
{
    public function __construct(private StripeClient $stripe) {}

    /**
     * The booking id travels as client_reference_id and metadata, so the
     * webhook can find the booking even if the session id was never stored.
     * The payment methods offered are chosen in the Stripe Dashboard.
     * The idempotency key includes the creation time, so a database reset that
     * reuses booking ids never collides with a key Stripe still remembers.
     */
    public function createDepositCheckout(Booking $booking, string $successUrl, string $cancelUrl, CarbonImmutable $expiresAt): DepositCheckout
    {
        $booking->loadMissing(['service', 'operator', 'customer']);

        $session = $this->stripe->checkout->sessions->create([
            'mode' => 'payment',
            'line_items' => [[
                'quantity' => 1,
                'price_data' => [
                    'currency' => 'eur',
                    'unit_amount' => $booking->deposit_cents,
                    'product_data' => [
                        'name' => __('Deposit: :service with :operator', [
                            'service' => $booking->service->name,
                            'operator' => $booking->operator->name,
                        ]),
                    ],
                ],
            ]],
            'customer_email' => $booking->customer?->email,
            'client_reference_id' => (string) $booking->id,
            'metadata' => ['booking_id' => (string) $booking->id],
            'expires_at' => $expiresAt->getTimestamp(),
            'success_url' => $successUrl,
            'cancel_url' => $cancelUrl,
        ], ['idempotency_key' => "deposit-checkout-{$booking->id}-{$booking->created_at?->getTimestamp()}"]);

        return new DepositCheckout($session->id, (string) $session->url);
    }

    public function refund(string $paymentIntentId, string $idempotencyKey): void
    {
        $this->stripe->refunds->create(['payment_intent' => $paymentIntentId], ['idempotency_key' => $idempotencyKey]);
    }
}
