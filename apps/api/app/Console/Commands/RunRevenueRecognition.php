<?php

namespace App\Console\Commands;

use App\Jobs\RecognizeRevenueChunkJob;
use App\Models\Subscription;
use Carbon\Carbon;
use Illuminate\Console\Command;

class RunRevenueRecognition extends Command
{
    protected $signature = 'recognition:run {--date= : Recognize everything elapsed before this date (defaults to today)}';
    protected $description = 'Advance pending revenue recognition slices to recognized, chunked per README §8.';

    public function handle(): int
    {
        $asOfDate = $this->option('date') ? Carbon::parse($this->option('date')) : now();

        $chunkCount = 0;

        Subscription::query()
            ->whereHas('recognitionSlices', fn ($q) => $q->where('status', 'pending')->where('slice_date', '<', $asOfDate->toDateString()))
            ->chunkById(1000, function ($subscriptions) use ($asOfDate, &$chunkCount) {
                $chunkCount++;
                RecognizeRevenueChunkJob::dispatch($subscriptions->pluck('id')->all(), $asOfDate->toDateString());
            });

        $this->info("Dispatched {$chunkCount} recognition chunk job(s) for date < {$asOfDate->toDateString()}.");

        return self::SUCCESS;
    }
}
