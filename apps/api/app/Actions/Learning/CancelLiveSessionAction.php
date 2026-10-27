<?php

namespace App\Actions\Learning;

use App\Jobs\NotifyLiveSessionAudienceJob;
use App\Models\LiveSession;
use Illuminate\Support\Facades\DB;

class CancelLiveSessionAction
{
    /** Idempotent. The caller authorizes (LiveSessionPolicy::cancel). Returns true only for the call that canceled it. */
    public function execute(LiveSession $session): bool
    {
        $canceledNow = DB::transaction(function () use ($session) {
            $locked = LiveSession::whereKey($session->id)->lockForUpdate()->first();

            if (! $locked || $locked->status === 'canceled') {
                return false;
            }

            $locked->update(['status' => 'canceled', 'canceled_at' => now()]);

            return true;
        });

        if ($canceledNow) {
            NotifyLiveSessionAudienceJob::dispatch($session->id, 'canceled')->afterCommit();
        }

        return $canceledNow;
    }
}
