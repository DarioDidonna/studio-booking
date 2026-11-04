<?php

namespace App\Exceptions;

use Exception;

class BookingNotStartedException extends Exception
{
    public function __construct()
    {
        parent::__construct(__('This appointment has not started yet.'));
    }
}
