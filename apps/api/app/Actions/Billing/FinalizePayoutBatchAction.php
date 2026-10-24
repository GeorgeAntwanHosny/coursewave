<?php

namespace App\Actions\Billing;

use App\Jobs\ReconcilePayoutBatchJob;
use App\Models\PayoutBatch;
use App\Notifications\AdminBillingNotification;
use App\Notifications\InstructorNotification;
use App\Notifications\PayoutProcessedNotification;
use App\Support\SafeNotify;
use Illuminate\Support\Facades\DB;

class FinalizePayoutBatchAction
{
    private const UNRESOLVED = ['submitted', 'uncertain', 'reconciling'];

    /**
     * The ONLY place a payout ledger entry is written. Same transaction as the
     * status change (convention #3). The paid_cents projection moves only when
     * the ledger insert genuinely inserted a row (convention #27), so calling
     * this twice is a no-op. The instructor and the admins are notified once,
     * after the transaction has committed, and a notification failure never affects the money.
     */
    public function markSucceeded(int $batchId, string $providerTransferId): void
    {
        $justSucceeded = DB::transaction(function () use ($batchId, $providerTransferId) {
            $batch = PayoutBatch::whereKey($batchId)->lockForUpdate()->first();

            if (! $batch || ! in_array($batch->status, self::UNRESOLVED, true)) {
                return false; // already succeeded/failed, or not yet submitted
            }

            $batch->update([
                'status' => 'succeeded',
                'provider_transfer_id' => $providerTransferId,
                'failure_reason' => null,
                'completed_at' => now(),
            ]);

            $inserted = DB::table('ledger_entries')->insertOrIgnore([[
                'instructor_id' => $batch->instructor_id,
                'entry_type' => 'payout',
                'amount_cents' => -$batch->amount_cents, // debit, stored negative
                'subscription_id' => null,
                'recognition_slice_id' => null,
                'payout_batch_id' => $batch->id,
                'idempotency_key' => "payout:{$batch->id}",
                'created_at' => $batch->claimed_at, // deterministic economic date
                'recorded_at' => now(),
            ]]);

            if ($inserted) {
                DB::statement(
                    'INSERT INTO instructor_balances (instructor_id, owed_cents, paid_cents, updated_at)
                     VALUES (?, 0, ?, ?)
                     ON CONFLICT (instructor_id) DO UPDATE SET
                        paid_cents = instructor_balances.paid_cents + EXCLUDED.paid_cents,
                        updated_at = EXCLUDED.updated_at',
                    [$batch->instructor_id, -$batch->amount_cents, now()]
                );
            }

            return true;
        });

        if ($justSucceeded) {
            $batch = PayoutBatch::with('instructor')->find($batchId);

            if ($batch?->instructor) {
                SafeNotify::send($batch->instructor, PayoutProcessedNotification::make($batch));
                SafeNotify::toAdmins(AdminBillingNotification::payoutProcessed($batch));
            }
        }
    }

    /** Provider explicitly declined. No ledger entry — the amount stays owed and rolls into the next run. */
    public function markFailed(int $batchId, string $reason): void
    {
        $justFailed = DB::transaction(function () use ($batchId, $reason) {
            $batch = PayoutBatch::whereKey($batchId)->lockForUpdate()->first();

            if (! $batch || ! in_array($batch->status, self::UNRESOLVED, true)) {
                return false;
            }

            $batch->update([
                'status' => 'failed',
                'failure_reason' => mb_substr($reason, 0, 500),
                'completed_at' => now(),
            ]);

            return true;
        });

        if ($justFailed) {
            $batch = PayoutBatch::with('instructor')->find($batchId);

            if ($batch?->instructor) {
                // The instructor gets a generic message; the provider's reason goes to admins only.
                SafeNotify::send($batch->instructor, InstructorNotification::payoutFailed($batch));
                SafeNotify::toAdmins(AdminBillingNotification::payoutFailed($batch, mb_substr($reason, 0, 200)));
            }
        }
    }

    /** Outcome unknown. Never failed, never succeeded — schedule reconciliation with backoff. */
    public function markUncertain(int $batchId, string $reason): void
    {
        $count = DB::transaction(function () use ($batchId, $reason) {
            $batch = PayoutBatch::whereKey($batchId)->lockForUpdate()->first();

            if (! $batch || ! in_array($batch->status, self::UNRESOLVED, true)) {
                return null;
            }

            $batch->update([
                'status' => 'uncertain',
                'failure_reason' => mb_substr($reason, 0, 500),
                'uncertain_count' => $batch->uncertain_count + 1,
            ]);

            return $batch->uncertain_count;
        });

        if ($count === null) {
            return;
        }

        if ($count <= (int) config('services.payouts.max_reconcile_attempts')) {
            ReconcilePayoutBatchJob::dispatch($batchId)->delay(now()->addMinutes(min(2 ** $count, 60)));

            return;
        }

        // Out of automatic attempts: it stays 'uncertain' (never auto-failed) and a human has to look.
        $batch = PayoutBatch::with('instructor')->find($batchId);

        if ($batch?->instructor) {
            SafeNotify::toAdmins(AdminBillingNotification::payoutStuck($batch, $count));
        }
    }
}
