<?php

namespace App\Services\Payments;

class CheckoutSessionResult
{
    public function __construct(
        public readonly string $id,
        public readonly ?string $url,
        public readonly string $paymentStatus, // 'paid' | 'unpaid'
        public readonly int $amountTotalCents,
        public readonly ?string $customerId,
        public readonly ?string $paymentIntentId,
    ) {}
}
