<?php

namespace App\Actions\Billing;

use App\Models\InstructorPayoutAccount;
use App\Notifications\InstructorNotification;
use App\Services\Payments\PaymentGatewayInterface;
use App\Support\SafeNotify;

class RefreshPayoutAccountStatusAction
{
    public function __construct(protected PaymentGatewayInterface $gateway) {}

    /** Tells the instructor only when the status actually flips, so a repeated refresh is silent. */
    public function execute(InstructorPayoutAccount $account): InstructorPayoutAccount
    {
        $wasEnabled = (bool) $account->payouts_enabled;

        $status = $this->gateway->retrievePayoutAccount($account->ref());
        $account->update(['payouts_enabled' => $status->payoutsEnabled]);
        $account = $account->fresh();

        if ($wasEnabled !== (bool) $account->payouts_enabled) {
            $instructor = $account->instructor;

            if ($instructor) {
                SafeNotify::send(
                    $instructor,
                    $account->payouts_enabled
                        ? InstructorNotification::payoutAccountReady()
                        : InstructorNotification::payoutAccountNeedsAttention()
                );
            }
        }

        return $account;
    }
}
