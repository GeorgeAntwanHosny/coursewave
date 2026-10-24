<?php

namespace App\Console\Commands;

use App\Actions\Billing\ClaimPayoutBatchAction;
use App\Jobs\SubmitPayoutBatchJob;
use App\Models\InstructorBalance;
use App\Models\User;
use App\Notifications\AdminBillingNotification;
use App\Support\SafeNotify;
use Carbon\Carbon;
use Illuminate\Console\Command;

class RunPayouts extends Command
{
    protected $signature = 'payouts:run {--date= : Period end (defaults to yesterday)} {--instructor= : Limit to one instructor id}';
    protected $description = 'Claim and submit payout batches. Safe to run concurrently or repeatedly — claims are DB-constrained.';

    public function handle(ClaimPayoutBatchAction $claim): int
    {
        $periodEnd = $this->option('date')
            ? Carbon::parse($this->option('date'))->startOfDay()
            : now()->subDay()->startOfDay();

        $minimum = (int) config('services.payouts.minimum_cents');
        $claimed = 0;
        $totalCents = 0;

        // The projection only selects CANDIDATES; the authoritative amount is
        // recomputed from the ledger inside ClaimPayoutBatchAction.
        InstructorBalance::query()
            ->whereRaw('owed_cents + paid_cents >= ?', [$minimum])
            ->when($this->option('instructor'), fn ($q, $id) => $q->where('instructor_id', $id))
            ->chunkById(200, function ($balances) use ($claim, $periodEnd, &$claimed, &$totalCents) {
                foreach ($balances as $balance) {
                    $instructor = User::find($balance->instructor_id);
                    if (! $instructor) {
                        continue;
                    }

                    $batch = $claim->execute($instructor, $periodEnd);
                    if ($batch) {
                        SubmitPayoutBatchJob::dispatch($batch->id);
                        $claimed++;
                        $totalCents += $batch->amount_cents;
                    }
                }
            }, 'instructor_id');

        $this->info("Claimed {$claimed} payout batch(es) for period ending {$periodEnd->toDateString()}.");

        if ($claimed > 0) {
            SafeNotify::toAdmins(AdminBillingNotification::payoutRun($claimed, $totalCents));
        }

        return self::SUCCESS;
    }
}
