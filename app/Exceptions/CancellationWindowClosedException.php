<?php

namespace App\Exceptions;

use App\Models\Booking;
use Exception;

class CancellationWindowClosedException extends Exception
{
    public function __construct()
    {
        parent::__construct(__('Bookings can be cancelled up to :hours hours before they start.', [
            'hours' => Booking::FREE_CANCELLATION_HOURS,
        ]));
    }
}
