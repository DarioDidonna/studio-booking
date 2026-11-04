<?php

namespace App\Enums;

use Carbon\CarbonInterface;

/**
 * ISO-8601 weekday numbers, matching Carbon's dayOfWeekIso.
 */
enum Weekday: int
{
    case Monday = 1;
    case Tuesday = 2;
    case Wednesday = 3;
    case Thursday = 4;
    case Friday = 5;
    case Saturday = 6;
    case Sunday = 7;

    public function label(): string
    {
        return __($this->name);
    }

    public static function fromDate(CarbonInterface $date): self
    {
        return self::from($date->dayOfWeekIso);
    }
}
