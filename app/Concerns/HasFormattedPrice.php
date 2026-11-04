<?php

namespace App\Concerns;

use Illuminate\Support\Number;

/**
 * For models that store a price in euro cents in `price_cents`.
 */
trait HasFormattedPrice
{
    public function formattedPrice(): string
    {
        $euros = $this->price_cents / 100;

        return Number::currency($euros, in: 'EUR', locale: 'it')
            ?: number_format($euros, 2, ',', '.').' €';
    }
}
