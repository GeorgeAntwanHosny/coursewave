<?php

namespace Tests\Fakes;

use App\Models\Plan;
use App\Models\User;
use App\Services\Payments\CheckoutSessionResult;
use App\Services\Payments\PaymentGatewayInterface;
use Illuminate\Support\Str;

class FakePaymentGateway implements PaymentGatewayInterface
{
    public static string $paymentStatus = 'paid';

    /** @var array<string, int> session_id => amount_cents */
    public static array $sessionAmounts = [];

    public function getProviderName(): string
    {
        return 'stripe'; // fake stands in for the stripe provider in tests
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

    public static function reset(): void
    {
        self::$paymentStatus = 'paid';
        self::$sessionAmounts = [];
    }
}
