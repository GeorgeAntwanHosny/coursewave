<?php

namespace App\Services\Payments;

use App\Models\Plan;
use App\Models\User;

interface PaymentGatewayInterface
{
    /** A short, stable identifier for this gateway — e.g. 'stripe', 'paypal'. Stored on subscriptions/webhook_events. */
    public function getProviderName(): string;

    public function createCheckoutSession(User $user, Plan $plan, string $successUrl, string $cancelUrl): CheckoutSessionResult;

    public function retrieveCheckoutSession(string $sessionId): CheckoutSessionResult;
}
