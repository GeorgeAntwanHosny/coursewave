<?php

namespace App\Services\Payments;

use App\Models\Plan;
use App\Models\User;
use App\Services\Payments\Exceptions\PayoutDeclinedException;
use App\Services\Payments\Exceptions\PayoutUncertainException;

interface PaymentGatewayInterface
{
    /** A short, stable identifier — e.g. 'stripe'. Stored on subscriptions/webhook_events/payout_batches. */
    public function getProviderName(): string;

    // ---- Inbound ----
    public function createCheckoutSession(User $user, Plan $plan, string $successUrl, string $cancelUrl): CheckoutSessionResult;

    public function retrieveCheckoutSession(string $sessionId): CheckoutSessionResult;

    // ---- Payout accounts ----
    /** Creates the provider-side connected account for an instructor. */
    public function createPayoutAccount(User $instructor): PayoutAccountRef;

    public function createPayoutOnboardingLink(PayoutAccountRef $account, string $returnUrl, string $refreshUrl): string;

    public function retrievePayoutAccount(PayoutAccountRef $account): PayoutAccountStatus;

    // ---- Outbound transfers ----
    /**
     * Must be idempotent on $idempotencyKey: retrying with the same key either
     * creates the transfer once or returns the original result.
     *
     * @throws PayoutDeclinedException  provider explicitly rejected it (no money moved)
     * @throws PayoutUncertainException outcome unknown (timeout, 5xx, connection loss)
     */
    public function createTransfer(string $accountId, int $amountCents, string $idempotencyKey): TransferResult;

    /** Finds a transfer previously created with this idempotency key, or null if none exists. */
    public function findTransferByIdempotencyKey(string $idempotencyKey): ?TransferResult;
}
