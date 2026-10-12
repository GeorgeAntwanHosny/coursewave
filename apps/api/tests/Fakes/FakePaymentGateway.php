<?php

namespace Tests\Fakes;

use App\Models\Plan;
use App\Models\User;
use App\Services\Payments\CheckoutSessionResult;
use App\Services\Payments\Exceptions\PayoutDeclinedException;
use App\Services\Payments\Exceptions\PayoutUncertainException;
use App\Services\Payments\PaymentGatewayInterface;
use App\Services\Payments\PayoutAccountRef;
use App\Services\Payments\PayoutAccountStatus;
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
                self::$transfers[$idempotencyKey] = $result; // it DID land, we just never heard back
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

    public static function reset(): void
    {
        self::$paymentStatus = 'paid';
        self::$sessionAmounts = [];
        self::$transferMode = 'success';
        self::$transfers = [];
        self::$refsSeen = [];
        self::$accountEnabled = true;
    }
}
