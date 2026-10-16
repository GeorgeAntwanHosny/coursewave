<?php

namespace App\Services\Payments;

use App\Models\Plan;
use App\Models\User;
use App\Services\Payments\Exceptions\PayoutDeclinedException;
use App\Services\Payments\Exceptions\PayoutUncertainException;
use App\Services\Payments\Exceptions\RefundDeclinedException;
use App\Services\Payments\Exceptions\RefundUncertainException;

interface PaymentGatewayInterface
{
    /** A short, stable identifier — e.g. 'stripe'. Stored on subscriptions/webhook_events/payout_batches/refunds. */
    public function getProviderName(): string;

    // ---- Inbound ----
    public function createCheckoutSession(User $user, Plan $plan, string $successUrl, string $cancelUrl): CheckoutSessionResult;

    public function retrieveCheckoutSession(string $sessionId): CheckoutSessionResult;

    // ---- Payout accounts ----
    public function createPayoutAccount(User $instructor): PayoutAccountRef;

    public function createPayoutOnboardingLink(PayoutAccountRef $account, string $returnUrl, string $refreshUrl): string;

    public function retrievePayoutAccount(PayoutAccountRef $account): PayoutAccountStatus;

    // ---- Outbound transfers ----
    /**
     * @throws PayoutDeclinedException  provider explicitly rejected it (no money moved)
     * @throws PayoutUncertainException outcome unknown (timeout, 5xx, connection loss)
     */
    public function createTransfer(string $accountId, int $amountCents, string $idempotencyKey): TransferResult;

    public function findTransferByIdempotencyKey(string $idempotencyKey): ?TransferResult;

    // ---- Refunds to students ----
    /**
     * Must be idempotent on $idempotencyKey (same key → the original refund, never a second one).
     *
     * @throws RefundDeclinedException  provider explicitly rejected it (no money moved)
     * @throws RefundUncertainException outcome unknown (timeout, 5xx, connection loss)
     */
    public function createRefund(string $paymentId, int $amountCents, string $idempotencyKey): RefundResult;

    /** Finds a refund previously created with this idempotency key against $paymentId, or null. */
    public function findRefundByIdempotencyKey(string $paymentId, string $idempotencyKey): ?RefundResult;
}
