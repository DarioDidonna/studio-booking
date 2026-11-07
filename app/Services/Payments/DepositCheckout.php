<?php

namespace App\Services\Payments;

/**
 * A hosted checkout page the customer is sent to.
 */
final readonly class DepositCheckout
{
    public function __construct(
        public string $sessionId,
        public string $url,
    ) {}
}
