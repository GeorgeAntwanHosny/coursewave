<?php

namespace App\Services\Payments\Exceptions;

/** Base for provider refund failures. Carries the raw provider details so they can be audited. */
abstract class RefundProviderException extends \RuntimeException
{
    public function __construct(
        string $message = '',
        public readonly ?int $httpStatus = null,
        public readonly ?string $providerRequestId = null,
        public readonly ?string $errorCode = null,
        public readonly ?array $response = null,
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
    }
}
