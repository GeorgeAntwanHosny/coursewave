<?php

namespace App\Actions\Billing;

use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use App\Services\Payments\CheckoutSessionResult;
use App\Services\Payments\PaymentGatewayInterface;

class InitiateCheckoutAction
{
    public function __construct(protected PaymentGatewayInterface $gateway) {}

    public function execute(User $user, Plan $plan): CheckoutSessionResult
    {
        $frontendUrl = rtrim(config('app.frontend_url'), '/');

        $result = $this->gateway->createCheckoutSession(
            $user,
            $plan,
            successUrl: "{$frontendUrl}/billing/success",
            cancelUrl: "{$frontendUrl}/billing/cancel",
        );

        Subscription::create([
            'student_id' => $user->id,
            'plan_id' => $plan->id,
            'status' => 'pending',
            'provider' => $this->gateway->getProviderName(),
            'provider_checkout_id' => $result->id,
            'total_amount_cents' => $plan->price_cents,
            'term_days' => $plan->term_days,
        ]);

        return $result;
    }
}
