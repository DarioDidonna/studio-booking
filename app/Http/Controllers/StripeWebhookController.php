<?php

namespace App\Http\Controllers;

use App\Services\Payments\DepositService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Stripe\Checkout\Session;
use Stripe\Exception\SignatureVerificationException;
use Stripe\Webhook;
use UnexpectedValueException;

/**
 * Stripe's events are the only source of truth for payments: the redirect
 * back from Checkout can be faked by anyone, a signed event cannot.
 */
class StripeWebhookController extends Controller
{
    public function __invoke(Request $request, DepositService $deposits): Response
    {
        try {
            $event = Webhook::constructEvent(
                $request->getContent(),
                (string) $request->header('Stripe-Signature'),
                (string) config('services.stripe.webhook_secret'),
            );
        } catch (SignatureVerificationException|UnexpectedValueException) {
            return response('Invalid payload or signature.', 400);
        }

        // Recording the event and acting on it commit together: a failure rolls
        // both back, so Stripe's retry is handled; a repeated delivery is ignored.
        DB::transaction(function () use ($event, $deposits): void {
            $isNew = DB::table('stripe_webhook_events')->insertOrIgnore([
                'id' => $event->id,
                'type' => $event->type,
                'processed_at' => Date::now(),
            ]) === 1;

            if (! $isNew) {
                return;
            }

            $session = $event->data->object;

            if (! $session instanceof Session) {
                return;
            }

            $bookingId = $session->metadata['booking_id'] ?? null;

            // Cards are paid on completion. Delayed methods such as SEPA debit
            // complete unpaid and report the outcome days later in a second event.
            match ($event->type) {
                'checkout.session.completed', 'checkout.session.async_payment_succeeded' => $session->payment_status === 'paid'
                    ? $deposits->handlePaidCheckout($session->id, $bookingId, (string) $session->payment_intent)
                    : null,
                'checkout.session.expired', 'checkout.session.async_payment_failed' => $deposits->handleExpiredCheckout($session->id, $bookingId),
                default => null,
            };
        });

        return response('OK');
    }
}
