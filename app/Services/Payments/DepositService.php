<?php

namespace App\Services\Payments;

use App\Exceptions\InvalidBookingTransitionException;
use App\Exceptions\PaymentUnavailableException;
use App\Models\Booking;
use App\Services\BookingService;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Log;
use Stripe\Checkout\Session;
use Stripe\Exception\ApiErrorException;

/**
 * Connects pending bookings with Stripe Checkout: opens the payment page,
 * and turns Stripe's events into booking changes.
 */
class DepositService
{
    public function __construct(
        private BookingService $bookings,
        private PaymentGateway $gateway,
    ) {}

    /**
     * Open the payment page of a pending booking. If Stripe cannot be reached
     * the booking is released at once, instead of blocking the slot until the
     * payment window runs out.
     *
     * Stripe wants a Checkout Session to live at least 30 minutes from its own
     * creation, so the window is counted from now, with a minute to spare.
     *
     * @throws PaymentUnavailableException
     */
    public function startCheckout(Booking $booking): DepositCheckout
    {
        $paymentDueAt = Date::now()->addMinutes(Booking::PAYMENT_WINDOW_MINUTES + 1);

        try {
            $checkout = $this->gateway->createDepositCheckout(
                $booking,
                route('bookings.index', ['payment' => 'success']),
                route('bookings.index', ['payment' => 'cancelled']),
                $paymentDueAt,
            );
        } catch (ApiErrorException $exception) {
            Log::warning('Stripe checkout could not be created', ['booking_id' => $booking->id, 'error' => $exception->getMessage()]);
            $this->release($booking, notifyCustomer: false);

            throw new PaymentUnavailableException($exception);
        }

        $booking->update([
            'stripe_checkout_session_id' => $checkout->sessionId,
            'stripe_checkout_url' => $checkout->url,
            'payment_due_at' => $paymentDueAt,
        ]);

        return $checkout;
    }

    /**
     * The customer finished Checkout. Cards are paid at once; delayed methods
     * such as SEPA debit finish unpaid and report the outcome days later, so
     * the slot stays held until then and the release command leaves it alone.
     *
     * A payment is never trusted blindly: an amount or currency other than the
     * deposit's is logged for a person to check instead of confirming.
     */
    public function handleCompletedCheckout(Session $session): void
    {
        $booking = $this->findBooking($session);

        if ($booking === null) {
            return;
        }

        if ($session->payment_status !== 'paid') {
            $booking->update(['payment_due_at' => null]);

            return;
        }

        if ($session->amount_total !== $booking->deposit_cents || $session->currency !== 'eur') {
            Log::error('Stripe payment does not match the deposit', [
                'booking_id' => $booking->id,
                'amount_paid' => $session->amount_total,
                'currency' => $session->currency,
            ]);

            return;
        }

        $this->bookings->confirmDeposit($booking, (string) $session->payment_intent);
    }

    /**
     * The session expired unpaid, or a delayed payment failed: free the slot.
     */
    public function handleFailedCheckout(Session $session): void
    {
        $booking = $this->findBooking($session);

        if ($booking !== null) {
            $this->release($booking);
        }
    }

    /**
     * Free the slot of an unpaid booking. It may already be confirmed,
     * cancelled or released by a parallel event: then there is nothing to do.
     *
     * @return bool Whether this call released it.
     */
    public function release(Booking $booking, bool $notifyCustomer = true): bool
    {
        try {
            $this->bookings->releaseUnpaid($booking, $notifyCustomer);
        } catch (InvalidBookingTransitionException) {
            return false;
        }

        return true;
    }

    /**
     * The session id is stored when checkout starts; the booking id in the
     * session metadata covers a crash between creating the session and storing it.
     */
    private function findBooking(Session $session): ?Booking
    {
        $bookingId = $session->metadata['booking_id'] ?? null;

        return Booking::query()->where('stripe_checkout_session_id', $session->id)->first()
            ?? (is_string($bookingId) && ctype_digit($bookingId) ? Booking::query()->whereNotNull('deposit_status')->find((int) $bookingId) : null);
    }
}
