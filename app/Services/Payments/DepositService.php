<?php

namespace App\Services\Payments;

use App\Exceptions\InvalidBookingTransitionException;
use App\Exceptions\PaymentUnavailableException;
use App\Models\Booking;
use App\Services\BookingService;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Log;
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
     * @throws PaymentUnavailableException
     */
    public function startCheckout(Booking $booking): DepositCheckout
    {
        try {
            $checkout = $this->gateway->createDepositCheckout(
                $booking,
                route('bookings.index', ['payment' => 'success']),
                route('bookings.index', ['payment' => 'cancelled']),
                $booking->payment_due_at ?? Date::now()->addMinutes(Booking::PAYMENT_WINDOW_MINUTES),
            );
        } catch (ApiErrorException $exception) {
            Log::warning('Stripe checkout could not be created', ['booking_id' => $booking->id, 'error' => $exception->getMessage()]);
            $this->release($booking);

            throw new PaymentUnavailableException($exception);
        }

        $booking->update([
            'stripe_checkout_session_id' => $checkout->sessionId,
            'stripe_checkout_url' => $checkout->url,
        ]);

        return $checkout;
    }

    /**
     * checkout.session.completed: the customer paid.
     */
    public function handlePaidCheckout(string $sessionId, ?string $bookingId, string $paymentIntentId): void
    {
        $booking = $this->findBooking($sessionId, $bookingId);

        if ($booking !== null) {
            $this->bookings->confirmDeposit($booking, $paymentIntentId);
        }
    }

    /**
     * checkout.session.expired: the payment window closed without a payment.
     */
    public function handleExpiredCheckout(string $sessionId, ?string $bookingId): void
    {
        $booking = $this->findBooking($sessionId, $bookingId);

        if ($booking !== null) {
            $this->release($booking);
        }
    }

    /**
     * Free the slot of an unpaid booking. It may already be confirmed,
     * cancelled or released by a parallel event: then there is nothing to do.
     */
    public function release(Booking $booking): void
    {
        try {
            $this->bookings->releaseUnpaid($booking);
        } catch (InvalidBookingTransitionException) {
            // Paid, cancelled or already released meanwhile.
        }
    }

    /**
     * The session id is stored when checkout starts; the booking id in the
     * session metadata covers a crash between creating the session and storing it.
     */
    private function findBooking(string $sessionId, ?string $bookingId): ?Booking
    {
        return Booking::query()->where('stripe_checkout_session_id', $sessionId)->first()
            ?? ($bookingId !== null && ctype_digit($bookingId) ? Booking::query()->whereNotNull('deposit_status')->find((int) $bookingId) : null);
    }
}
