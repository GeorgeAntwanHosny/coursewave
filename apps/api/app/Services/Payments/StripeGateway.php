<?php

namespace App\Services\Payments;

use App\Models\Plan;
use App\Models\User;
use App\Services\Payments\Exceptions\PayoutDeclinedException;
use App\Services\Payments\Exceptions\PayoutUncertainException;
use Illuminate\Support\Facades\Log;
use Stripe\Checkout\Session;
use Stripe\Exception\ApiConnectionException;
use Stripe\Exception\ApiErrorException;
use Stripe\StripeClient;

class StripeGateway implements PaymentGatewayInterface
{
    protected StripeClient $client;

    public function __construct()
    {
        $this->client = new StripeClient(config('services.stripe.secret'));
    }

    public function getProviderName(): string
    {
        return 'stripe';
    }

    // ------------------------------------------------------------------
    // Inbound
    // ------------------------------------------------------------------

    public function createCheckoutSession(User $user, Plan $plan, string $successUrl, string $cancelUrl): CheckoutSessionResult
    {
        $session = $this->client->checkout->sessions->create([
            'mode' => 'payment',
            'customer_email' => $user->email,
            'line_items' => [[
                'price_data' => [
                    'currency' => 'usd',
                    'unit_amount' => $plan->price_cents,
                    'product_data' => ['name' => "CourseWave — {$plan->name}"],
                ],
                'quantity' => 1,
            ]],
            'metadata' => ['student_id' => $user->id, 'plan_id' => $plan->id],
            'success_url' => $successUrl . '?session_id={CHECKOUT_SESSION_ID}',
            'cancel_url' => $cancelUrl,
        ]);

        return $this->toResult($session);
    }

    public function retrieveCheckoutSession(string $sessionId): CheckoutSessionResult
    {
        $session = $this->client->checkout->sessions->retrieve($sessionId, ['expand' => ['payment_intent']]);

        return $this->toResult($session);
    }

    // ------------------------------------------------------------------
    // Payout accounts — new accounts use Accounts v2; legacy v1 accounts keep working
    // ------------------------------------------------------------------

    public function createPayoutAccount(User $instructor): PayoutAccountRef
    {
        $data = $this->v2Request('post', '/v2/core/accounts', [
            'contact_email' => $instructor->email,
            'display_name' => $instructor->name,
            'dashboard' => 'express',
            'identity' => [
                'country' => config('services.stripe.connect_country'),
                'entity_type' => config('services.stripe.connect_entity_type'),
            ],
            'configuration' => [
                'recipient' => [
                    'capabilities' => [
                        'stripe_balance' => ['stripe_transfers' => ['requested' => true]],
                    ],
                ],
            ],
            'defaults' => [
                'currency' => 'usd',
                // Stripe requires application/application when dashboard is 'express':
                // the platform bears fees and losses, as with v1 Express accounts.
                'responsibilities' => ['fees_collector' => 'application', 'losses_collector' => 'application'],
            ],
            'metadata' => ['instructor_id' => (string) $instructor->id],
            'include' => ['configuration.recipient', 'requirements'],
        ], [
            // A retry after a crash before we saved the row returns the same account
            // instead of creating an orphan. Stripe keeps idempotency keys ~24h.
            'idempotency_key' => "payout-account-v2:{$instructor->id}",
        ]);

        return new PayoutAccountRef($data['id'], 'v2');
    }

    public function createPayoutOnboardingLink(PayoutAccountRef $account, string $returnUrl, string $refreshUrl): string
    {
        if ($account->version === 'v2') {
            $data = $this->v2Request('post', '/v2/core/account_links', [
                'account' => $account->id,
                'use_case' => [
                    'type' => 'account_onboarding',
                    'account_onboarding' => [
                        'configurations' => ['recipient'],
                        'return_url' => $returnUrl,
                        'refresh_url' => $refreshUrl,
                    ],
                ],
            ]);

            return $data['url'];
        }

        // Legacy v1 account.
        return $this->tolerateAccountsV2Advisory(function () use ($account, $returnUrl, $refreshUrl) {
            return $this->client->accountLinks->create([
                'account' => $account->id,
                'return_url' => $returnUrl,
                'refresh_url' => $refreshUrl,
                'type' => 'account_onboarding',
            ])->url;
        });
    }

    public function retrievePayoutAccount(PayoutAccountRef $account): PayoutAccountStatus
    {
        if ($account->version === 'v2') {
            $data = $this->v2Request(
                'get',
                "/v2/core/accounts/{$account->id}?include=configuration.recipient&include=requirements"
            );

            $balance = data_get($data, 'configuration.recipient.capabilities.stripe_balance', []);

            // We need to RECEIVE transfers and to pay out to the bank; both must be active.
            $enabled = data_get($balance, 'stripe_transfers.status') === 'active'
                && data_get($balance, 'payouts.status') === 'active';

            return new PayoutAccountStatus($account->id, $enabled);
        }

        // Legacy v1 account.
        return $this->tolerateAccountsV2Advisory(function () use ($account) {
            $v1 = $this->client->accounts->retrieve($account->id);

            return new PayoutAccountStatus($v1->id, (bool) $v1->payouts_enabled);
        });
    }

    // ------------------------------------------------------------------
    // Transfers — unchanged: /v1/transfers works for both account generations
    // ------------------------------------------------------------------

    public function createTransfer(string $accountId, int $amountCents, string $idempotencyKey): TransferResult
    {
        try {
            $transfer = $this->client->transfers->create([
                'amount' => $amountCents,
                'currency' => 'usd',
                'destination' => $accountId,
                // Stripe can't look transfers up by idempotency key, so the key is
                // also the transfer_group — that is what reconciliation searches by.
                'transfer_group' => $idempotencyKey,
                'metadata' => ['payout_key' => $idempotencyKey],
            ], ['idempotency_key' => $idempotencyKey]);
        } catch (ApiConnectionException $e) {
            throw new PayoutUncertainException(
                $e->getMessage(), null, $e->getRequestId(), null, $e->getJsonBody(), $e
            );
        } catch (ApiErrorException $e) {
            $status = $e->getHttpStatus();
            // 5xx / 429 / no status: Stripe may or may not have processed it → uncertain.
            // Other 4xx: Stripe guarantees the request failed → explicit decline.
            $class = ($status === null || $status >= 500 || $status === 429)
                ? PayoutUncertainException::class
                : PayoutDeclinedException::class;

            throw new $class($e->getMessage(), $status, $e->getRequestId(), $e->getStripeCode(), $e->getJsonBody(), $e);
        }

        return new TransferResult(
            $transfer->id,
            (int) $transfer->amount,
            $accountId,
            $transfer->toArray(),
            $this->requestIdFrom($transfer),
        );
    }

    public function findTransferByIdempotencyKey(string $idempotencyKey): ?TransferResult
    {
        $list = $this->client->transfers->all(['transfer_group' => $idempotencyKey, 'limit' => 1]);
        $transfer = $list->data[0] ?? null;

        return $transfer
            ? new TransferResult(
                $transfer->id,
                (int) $transfer->amount,
                (string) $transfer->destination,
                $transfer->toArray(),
                $this->requestIdFrom($list),
            )
            : null;
    }

    // ------------------------------------------------------------------
    // Helpers
    // ------------------------------------------------------------------

    /**
     * Calls a v2 endpoint through the SDK's raw request (stripe-php >= 16), sending
     * the pinned v2 API version. Returns the decoded JSON body.
     */
    protected function v2Request(string $method, string $path, ?array $params = null, array $opts = []): array
    {
        $opts['stripe_version'] = config('services.stripe.v2_api_version');

        try {
            // GET requests must pass null params; the query string is part of $path.
            $response = $this->client->rawRequest($method, $path, $method === 'get' ? null : $params, $opts);
        } catch (ApiErrorException $e) {
            if (str_contains((string) $e->getStripeCode(), 'accounts_v2_access_blocked')
                || str_contains($e->getMessage(), 'Accounts v2 is not enabled')) {
                throw new \RuntimeException(
                    'Stripe Accounts v2 is not enabled for this platform. Enable it in the Stripe dashboard (Connect settings).',
                    0,
                    $e
                );
            }

            throw $e;
        }

        return json_decode($response->body, true) ?? [];
    }

    /**
     * Only for LEGACY v1 accounts: stripe-php emits a PHP warning advising Accounts v2
     * on v1 account endpoints, which Laravel turns into an ErrorException. We swallow
     * only that specific advisory (logged as a notice); every other warning still
     * reaches Laravel's handler. Remove together with the v1 branches once no v1
     * accounts remain.
     */
    protected function tolerateAccountsV2Advisory(callable $callback): mixed
    {
        set_error_handler(function (int $severity, string $message) {
            if (str_contains($message, 'Accounts v2')) {
                Log::notice('Stripe advisory (Accounts v2 recommended, legacy v1 account): ' . $message);

                return true;
            }

            return false;
        });

        try {
            return $callback();
        } finally {
            restore_error_handler();
        }
    }

    protected function requestIdFrom(object $stripeObject): ?string
    {
        $headers = $stripeObject->getLastResponse()?->headers ?? null;

        return $headers['Request-Id'] ?? $headers['request-id'] ?? null;
    }

    protected function toResult(Session $session): CheckoutSessionResult
    {
        return new CheckoutSessionResult(
            id: $session->id,
            url: $session->url,
            paymentStatus: $session->payment_status,
            amountTotalCents: $session->amount_total ?? 0,
            customerId: $session->customer,
            paymentIntentId: is_string($session->payment_intent) ? $session->payment_intent : ($session->payment_intent->id ?? null),
        );
    }
}
