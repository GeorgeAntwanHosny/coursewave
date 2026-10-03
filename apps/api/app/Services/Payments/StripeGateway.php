<?php

namespace App\Services\Payments;

use App\Models\Plan;
use App\Models\User;
use Stripe\Checkout\Session;
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
            'metadata' => [
                'student_id' => $user->id,
                'plan_id' => $plan->id,
            ],
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
