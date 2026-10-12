<?php

namespace App\Services\Payments;

/** Identifies a provider-side payout account. `version` is meaningful only to the gateway that issued it. */
class PayoutAccountRef
{
    public function __construct(
        public readonly string $id,
        public readonly string $version = 'v1',
    ) {}
}
