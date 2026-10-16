<?php

namespace App\Services\Payments;

class RefundResult
{
    public function __construct(
        public readonly string $refundId,
        public readonly int $amountCents,
        public readonly string $paymentId,
        public readonly string $status,          // provider status, e.g. succeeded | pending | failed | canceled
        public readonly ?array $response = null, // raw provider payload, for the audit log
        public readonly ?string $requestId = null,
    ) {}
}
