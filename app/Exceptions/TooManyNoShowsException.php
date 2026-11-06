<?php

namespace App\Exceptions;

use Exception;

/**
 * The customer missed too many appointments to keep booking online.
 */
class TooManyNoShowsException extends Exception
{
    public function __construct()
    {
        parent::__construct(__('You missed several appointments, so online booking is paused. Please contact the barbershop to book.'));
    }
}
