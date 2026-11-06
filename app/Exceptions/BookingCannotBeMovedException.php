<?php

namespace App\Exceptions;

use App\Models\Booking;
use Exception;

/**
 * The booking is no longer open, or the customer is past the free change window.
 */
class BookingCannotBeMovedException extends Exception
{
    public static function windowClosed(): self
    {
        return new self(__('Upcoming bookings can be moved up to :hours hours before they start.', [
            'hours' => Booking::FREE_CANCELLATION_HOURS,
        ]));
    }

    public static function notOpen(): self
    {
        return new self(__('This booking is no longer open, so it cannot be moved.'));
    }
}
