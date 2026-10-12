<?php

namespace App\Services\Payments;

class TransferResult
{
    public function __construct(
        public readonly string $transferId,
        public readonly int $amountCents,
        public readonly string $accountId,
        public readonly ?array $response = null,   // raw provider payload, for the audit log
        public readonly ?string $requestId = null, // provider request id, for support tickets
    ) {}
}
