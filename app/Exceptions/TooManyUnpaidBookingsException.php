<?php

namespace App\Exceptions;

use Exception;

/**
 * The customer let too many bookings lapse without paying the deposit today.
 */
class TooManyUnpaidBookingsException extends Exception
{
    public function __construct()
    {
        parent::__construct(__('Several of your bookings today lapsed without a deposit, so online booking is paused until tomorrow.'));
    }
}
