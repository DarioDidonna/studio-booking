<?php

namespace App\Enums;

/**
 * What happened to the deposit a customer pays when booking online.
 */
enum DepositStatus: string
{
    /** Checkout started, payment not received yet. */
    case Unpaid = 'unpaid';

    /** Paid, held until the appointment. */
    case Paid = 'paid';

    /** Customer showed up: the deposit is taken off the bill. */
    case Applied = 'applied';

    /** Customer did not show up: the barbershop keeps it. */
    case Kept = 'kept';

    /** Returned to the card. */
    case Refunded = 'refunded';

    public function label(): string
    {
        return match ($this) {
            self::Unpaid => __('Awaiting payment'),
            self::Paid => __('Paid'),
            self::Applied => __('Taken off the bill'),
            self::Kept => __('Kept'),
            self::Refunded => __('Refunded'),
        };
    }
}
