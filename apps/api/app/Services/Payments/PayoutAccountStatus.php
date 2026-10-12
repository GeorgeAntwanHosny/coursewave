<?php

namespace App\Services\Payments;

class PayoutAccountStatus
{
    public function __construct(
        public readonly string $accountId,
        public readonly bool $payoutsEnabled,
    ) {}
}
