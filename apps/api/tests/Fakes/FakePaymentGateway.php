<?php

namespace Tests\Fakes;

use App\Models\Plan;
use App\Models\User;
use App\Services\Payments\CheckoutSessionResult;
use App\Services\Payments\Exceptions\PayoutDeclinedException;
use App\Services\Payments\Exceptions\PayoutUncertainException;
use App\Services\Payments\Exceptions\RefundDeclinedException;
use App\Services\Payments\Exceptions\RefundUncertainException;
use App\Services\Payments\PaymentGatewayInterface;
use App\Services\Payments\PayoutAccountRef;
use App\Services\Payments\PayoutAccountStatus;
use App\Services\Payments\RefundResult;
use App\Services\Payments\TransferResult;
use Illuminate\Support\Str;

class FakePaymentGateway implements PaymentGatewayInterface
{
    public static string $paymentStatus = 'paid';

    /** @var array<string, int> session_id => amount_cents */
    public static array $sessionAmounts = [];

    /** success | decline | timeout_not_created | timeout_created */
    public static string $transferMode = 'success';

    /** @var array<string, TransferResult> idempotency key => transfer actually "created" at the provider */
    public static array $transfers = [];

    /** success | decline | timeout_not_created | timeout_created */
    public static string $refundMode = 'success';

    /** @var array<string, RefundResult> idempotency key => refund actually "created" at the provider */
    public static array $refunds = [];

    /** @var PayoutAccountRef[] refs the app passed for onboarding links / status checks, in order */
    public static array $refsSeen = [];

    public static bool $accountEnabled = true;

    public function getProviderName(): string
    {
        return 'stripe';
    }

    public function createCheckoutSession(User $user, Plan $plan, string $successUrl, string $cancelUrl): CheckoutSessionResult
    {
        $sessionId = 'cs_test_' . Str::random(20);
        self::$sessionAmounts[$sessionId] = $plan->price_cents;

        return new CheckoutSessionResult(
            id: $sessionId,
            url: "https://checkout.stripe.com/fake/{$sessionId}",
            paymentStatus: 'unpaid',
            amountTotalCents: $plan->price_cents,
            customerId: 'cus_fake',
            paymentIntentId: null,
        );
    }

    public function retrieveCheckoutSession(string $sessionId): CheckoutSessionResult
    {
        return new CheckoutSessionResult(
            id: $sessionId,
            url: null,
            paymentStatus: self::$paymentStatus,
            amountTotalCents: self::$sessionAmounts[$sessionId] ?? 1999,
            customerId: 'cus_fake',
            paymentIntentId: 'pi_fake_' . Str::random(10),
        );
    }

    public function createPayoutAccount(User $instructor): PayoutAccountRef
    {
        return new PayoutAccountRef('acct_fake_' . Str::random(8), 'v2');
    }

    public function createPayoutOnboardingLink(PayoutAccountRef $account, string $returnUrl, string $refreshUrl): string
    {
        self::$refsSeen[] = $account;

        return "https://connect.stripe.com/fake/{$account->id}";
    }

    public function retrievePayoutAccount(PayoutAccountRef $account): PayoutAccountStatus
    {
        self::$refsSeen[] = $account;

        return new PayoutAccountStatus($account->id, self::$accountEnabled);
    }

    public function createTransfer(string $accountId, int $amountCents, string $idempotencyKey): TransferResult
    {
        // Mirrors real provider idempotency: same key → original result, never a second transfer.
        if (isset(self::$transfers[$idempotencyKey])) {
            return self::$transfers[$idempotencyKey];
        }

        $id = 'tr_fake_' . Str::random(10);
        $result = new TransferResult($id, $amountCents, $accountId, [
            'id' => $id,
            'object' => 'transfer',
            'amount' => $amountCents,
            'currency' => 'usd',
            'destination' => $accountId,
            'transfer_group' => $idempotencyKey,
        ], 'req_fake_' . Str::random(8));

        switch (self::$transferMode) {
            case 'decline':
                throw new PayoutDeclinedException(
                    'Insufficient funds in platform balance.',
                    400,
                    'req_fake_decline',
                    'balance_insufficient',
                    ['error' => ['code' => 'balance_insufficient', 'type' => 'invalid_request_error']],
                );
            case 'timeout_not_created':
                throw new PayoutUncertainException('Request timed out.');
            case 'timeout_created':
                self::$transfers[$idempotencyKey] = $result;
                throw new PayoutUncertainException('Request timed out.');
            default:
                self::$transfers[$idempotencyKey] = $result;

                return $result;
        }
    }

    public function findTransferByIdempotencyKey(string $idempotencyKey): ?TransferResult
    {
        return self::$transfers[$idempotencyKey] ?? null;
    }

    public function createRefund(string $paymentId, int $amountCents, string $idempotencyKey): RefundResult
    {
        // Mirrors real provider idempotency: same key → original refund, never a second one.
        if (isset(self::$refunds[$idempotencyKey])) {
            return self::$refunds[$idempotencyKey];
        }

        $id = 're_fake_' . Str::random(10);
        $result = new RefundResult($id, $amountCents, $paymentId, 'succeeded', [
            'id' => $id,
            'object' => 'refund',
            'amount' => $amountCents,
            'currency' => 'usd',
            'payment_intent' => $paymentId,
            'status' => 'succeeded',
            'metadata' => ['refund_key' => $idempotencyKey],
        ], 'req_fake_' . Str::random(8));

        switch (self::$refundMode) {
            case 'decline':
                throw new RefundDeclinedException(
                    'Charge has already been refunded.',
                    400,
                    'req_fake_refund_decline',
                    'charge_already_refunded',
                    ['error' => ['code' => 'charge_already_refunded', 'type' => 'invalid_request_error']],
                );
            case 'timeout_not_created':
                throw new RefundUncertainException('Request timed out.');
            case 'timeout_created':
                self::$refunds[$idempotencyKey] = $result; // it DID land, we just never heard back
                throw new RefundUncertainException('Request timed out.');
            default:
                self::$refunds[$idempotencyKey] = $result;

                return $result;
        }
    }

    public function findRefundByIdempotencyKey(string $paymentId, string $idempotencyKey): ?RefundResult
    {
        return self::$refunds[$idempotencyKey] ?? null;
    }

    public static function reset(): void
    {
        self::$paymentStatus = 'paid';
        self::$sessionAmounts = [];
        self::$transferMode = 'success';
        self::$transfers = [];
        self::$refundMode = 'success';
        self::$refunds = [];
        self::$refsSeen = [];
        self::$accountEnabled = true;
    }
}
