<?php

namespace App\Actions\Billing;

use App\Models\Subscription;
use App\Services\Payments\PaymentGatewayInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ConfirmSubscriptionAction
{
    public function __construct(
        protected PaymentGatewayInterface $gateway,
        protected GenerateRecognitionSlicesAction $generateSlices,
        protected CancelSubscriptionAction $cancelSubscription,
    ) {}

    public function execute(string $providerCheckoutId, ?int $expectedStudentId = null): Subscription
    {
        $subscription = Subscription::where('provider_checkout_id', $providerCheckoutId)
            ->where('provider', $this->gateway->getProviderName())
            ->first();

        if (! $subscription) {
            throw ValidationException::withMessages([
                'session_id' => ['No checkout session found for this account.'],
            ]);
        }

        if ($expectedStudentId !== null && $subscription->student_id !== $expectedStudentId) {
            throw ValidationException::withMessages([
                'session_id' => ['This checkout session does not belong to the current account.'],
            ]);
        }

        if ($subscription->status === 'active') {
            return $subscription;
        }

        $session = $this->gateway->retrieveCheckoutSession($providerCheckoutId);

        if ($session->paymentStatus !== 'paid') {
            throw ValidationException::withMessages([
                'session_id' => ['Payment has not completed for this checkout session.'],
            ]);
        }

        return DB::transaction(function () use ($subscription, $session) {
            // Cancel any other active subscription for this student FIRST —
            // the partial unique index requires status to clear 'active'
            // before this one can take it. Order matters here.
            $priorActive = Subscription::where('student_id', $subscription->student_id)
                ->where('status', 'active')
                ->where('id', '!=', $subscription->id)
                ->lockForUpdate()
                ->first();

            if ($priorActive) {
                $this->cancelSubscription->execute($priorActive);
            }

            $subscription->update([
                'status' => 'active',
                'provider_payment_id' => $session->paymentIntentId,
                'total_amount_cents' => $session->amountTotalCents,
                'starts_at' => now()->toDateString(),
                'ends_at' => now()->addDays($subscription->term_days)->toDateString(),
            ]);

            $this->generateSlices->execute($subscription->fresh());

            return $subscription->fresh();
        });
    }
}
