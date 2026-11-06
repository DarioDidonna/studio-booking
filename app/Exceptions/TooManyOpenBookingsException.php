<?php

namespace App\Exceptions;

use App\Models\Booking;
use Exception;

/**
 * The customer already holds the maximum number of upcoming bookings.
 */
class TooManyOpenBookingsException extends Exception
{
    public function __construct()
    {
        parent::__construct(__('You already have :count upcoming bookings. Cancel one to book another.', ['count' => Booking::MAX_UPCOMING_PER_CUSTOMER]));
    }
}
