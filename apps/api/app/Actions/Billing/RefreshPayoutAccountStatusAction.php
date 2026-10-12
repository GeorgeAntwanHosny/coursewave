<?php

namespace App\Actions\Billing;

use App\Models\InstructorPayoutAccount;
use App\Services\Payments\PaymentGatewayInterface;

class RefreshPayoutAccountStatusAction
{
    public function __construct(protected PaymentGatewayInterface $gateway) {}

    public function execute(InstructorPayoutAccount $account): InstructorPayoutAccount
    {
        $status = $this->gateway->retrievePayoutAccount($account->ref());
        $account->update(['payouts_enabled' => $status->payoutsEnabled]);

        return $account->fresh();
    }
}
