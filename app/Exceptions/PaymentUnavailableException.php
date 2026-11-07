<?php

namespace App\Exceptions;

use Exception;
use Throwable;

/**
 * The payment page could not be opened; the booking was released.
 */
class PaymentUnavailableException extends Exception
{
    public function __construct(?Throwable $previous = null)
    {
        parent::__construct(__('Online payment is not available right now, so the time was not booked. Please try again in a few minutes.'), previous: $previous);
    }
}
