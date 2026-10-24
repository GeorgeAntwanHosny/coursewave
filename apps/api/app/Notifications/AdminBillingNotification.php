<?php

namespace App\Notifications;

use App\Filament\Admin\Pages\PayoutBatches;
use App\Filament\Admin\Pages\Refunds;
use App\Filament\Admin\Pages\Subscriptions;
use App\Models\PayoutBatch;
use App\Models\Refund;
use App\Models\Subscription;

/**
 * Money events for admins: payments, cancellations, refunds and payouts. Never comments.
 * One factory per event; every link opens the matching admin page.
 */
final class AdminBillingNotification extends AppNotification
{
    public static function newSubscription(Subscription $subscription, ?string $switchedFrom = null): self
    {
        $subscription->loadMissing('student', 'plan');

        $body = "{$subscription->student->name} subscribed to {$subscription->plan->name} (" . self::usd($subscription->total_amount_cents) . ').';
        if ($switchedFrom) {
            $body .= " Switched from {$switchedFrom}.";
        }

        return new self(
            'admin_new_subscription',
            'New subscription',
            $body,
            Subscriptions::getUrl(panel: 'admin'),
            'heroicon-o-credit-card',
            'success',
        );
    }

    public static function subscriptionCanceled(Subscription $subscription, ?Refund $refund, string $reason): self
    {
        $subscription->loadMissing('student', 'plan');

        $by = $reason === 'admin_cancel' ? 'An admin canceled' : "{$subscription->student->name} canceled";
        $outcome = $refund
            ? 'Refund of ' . self::usd($refund->amount_cents) . ' queued.'
            : 'No unused days, so nothing to refund.';

        return new self(
            'admin_subscription_canceled',
            'Subscription canceled',
            "{$by} {$subscription->plan->name} for {$subscription->student->name}. {$outcome}",
            Subscriptions::getUrl(panel: 'admin'),
            'heroicon-o-x-circle',
            'warning',
        );
    }

    public static function payoutProcessed(PayoutBatch $batch): self
    {
        $batch->loadMissing('instructor');

        return new self(
            'admin_payout_processed',
            'Payout sent',
            self::usd($batch->amount_cents) . " was sent to {$batch->instructor->name}.",
            PayoutBatches::getUrl(panel: 'admin'),
            'heroicon-o-banknotes',
            'success',
        );
    }

    public static function payoutFailed(PayoutBatch $batch, string $reason): self
    {
        $batch->loadMissing('instructor');

        return new self(
            'admin_payout_failed',
            'Payout failed',
            self::usd($batch->amount_cents) . " to {$batch->instructor->name} was declined: {$reason}. The amount stays owed and rolls into the next run.",
            PayoutBatches::getUrl(panel: 'admin'),
            'heroicon-o-exclamation-circle',
            'danger',
        );
    }

    public static function payoutStuck(PayoutBatch $batch, int $attempts): self
    {
        $batch->loadMissing('instructor');

        return new self(
            'admin_payout_stuck',
            'Payout needs attention',
            "Payout #{$batch->id} (" . self::usd($batch->amount_cents) . " to {$batch->instructor->name}) is still unconfirmed after {$attempts} reconcile attempt(s). Check Stripe, then reconcile it.",
            PayoutBatches::getUrl(panel: 'admin'),
            'heroicon-o-exclamation-triangle',
            'warning',
        );
    }

    public static function refundProcessed(Refund $refund): self
    {
        $refund->loadMissing('student');

        return new self(
            'admin_refund_processed',
            'Refund sent',
            self::usd($refund->amount_cents) . " was refunded to {$refund->student->name}.",
            Refunds::getUrl(panel: 'admin'),
            'heroicon-o-receipt-refund',
            'success',
        );
    }

    public static function refundFailed(Refund $refund, string $reason): self
    {
        $refund->loadMissing('student');

        return new self(
            'admin_refund_failed',
            'Refund failed',
            self::usd($refund->amount_cents) . " refund for {$refund->student->name} was declined: {$reason}. Resolve it in the Stripe dashboard.",
            Refunds::getUrl(panel: 'admin'),
            'heroicon-o-exclamation-circle',
            'danger',
        );
    }

    public static function refundStuck(Refund $refund): self
    {
        $refund->loadMissing('student');

        return new self(
            'admin_refund_stuck',
            'Refund needs attention',
            "Refund #{$refund->id} (" . self::usd($refund->amount_cents) . " for {$refund->student->name}) is unconfirmed and automatic checks have stopped. Verify it in Stripe before resending.",
            Refunds::getUrl(panel: 'admin'),
            'heroicon-o-exclamation-triangle',
            'warning',
        );
    }

    public static function payoutRun(int $batches, int $totalCents): self
    {
        return new self(
            'admin_payout_run',
            'Payout run started',
            "Claimed {$batches} payout batch(es) totalling " . self::usd($totalCents) . '.',
            PayoutBatches::getUrl(panel: 'admin'),
            'heroicon-o-paper-airplane',
            'info',
        );
    }
}
