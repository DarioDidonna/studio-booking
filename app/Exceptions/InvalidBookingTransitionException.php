<?php

namespace App\Exceptions;

use App\Enums\BookingStatus;
use Exception;

class InvalidBookingTransitionException extends Exception
{
    public function __construct(BookingStatus $from, BookingStatus $to)
    {
        parent::__construct(__('A :from booking cannot become :to.', [
            'from' => $from->value,
            'to' => $to->value,
        ]));
    }
}
