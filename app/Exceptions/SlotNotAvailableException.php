<?php

namespace App\Exceptions;

use Exception;

/**
 * The requested start time is outside working hours, in the past,
 * or taken by another booking or by time off.
 */
class SlotNotAvailableException extends Exception
{
    public function __construct()
    {
        parent::__construct(__('The selected time is no longer available. Please choose another one.'));
    }
}
