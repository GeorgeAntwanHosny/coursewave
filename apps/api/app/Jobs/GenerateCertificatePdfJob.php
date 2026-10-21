<?php

namespace App\Jobs;

use App\Actions\Learning\GenerateCertificatePdfAction;
use App\Models\Certificate;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;

class GenerateCertificatePdfJob implements ShouldQueue
{
    use Dispatchable, Queueable;

    public int $tries = 3;

    public function __construct(public int $certificateId, public bool $force = false) {}

    /** @return int[] */
    public function backoff(): array
    {
        return [30, 120];
    }

    public function handle(GenerateCertificatePdfAction $generate): void
    {
        $certificate = Certificate::find($this->certificateId);

        if (! $certificate) {
            return;
        }

        $generate->execute($certificate, $this->force);
    }
}
