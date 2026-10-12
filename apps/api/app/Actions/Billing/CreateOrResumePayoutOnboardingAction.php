<?php

namespace App\Actions\Billing;

use App\Models\InstructorPayoutAccount;
use App\Models\User;
use App\Services\Payments\PaymentGatewayInterface;

class CreateOrResumePayoutOnboardingAction
{
    public function __construct(protected PaymentGatewayInterface $gateway) {}

    public function execute(User $instructor, string $returnUrl, string $refreshUrl): string
    {
        $account = InstructorPayoutAccount::where('instructor_id', $instructor->id)->first();

        if (! $account) {
            $ref = $this->gateway->createPayoutAccount($instructor);

            $account = InstructorPayoutAccount::create([
                'instructor_id' => $instructor->id,
                'provider' => $this->gateway->getProviderName(),
                'provider_account_id' => $ref->id,
                'provider_account_version' => $ref->version,
                'payouts_enabled' => false,
            ]);
        }

        return $this->gateway->createPayoutOnboardingLink($account->ref(), $returnUrl, $refreshUrl);
    }
}
