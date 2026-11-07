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
        return self::formatCents($this->price_cents);
    }

    /**
     * Euro cents as an Italian price, with a fallback for when intl is missing.
     */
    public static function formatCents(int $cents): string
    {
        $euros = $cents / 100;

        return Number::currency($euros, in: 'EUR', locale: 'it')
            ?: number_format($euros, 2, ',', '.').' €';
    }
}
