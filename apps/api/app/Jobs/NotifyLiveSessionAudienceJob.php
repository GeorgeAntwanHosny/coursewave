<?php

namespace App\Jobs;

use App\Actions\Learning\NotifyLiveSessionAudienceAction;
use App\Models\LiveSession;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;

class NotifyLiveSessionAudienceJob implements ShouldQueue
{
    use Dispatchable, Queueable;

    // At-most-once: a retry after a partial send would notify some students twice.
    public int $tries = 1;

    public function __construct(public int $sessionId, public string $event) {}

    public function handle(NotifyLiveSessionAudienceAction $notify): void
    {
        $session = LiveSession::find($this->sessionId);

        if (! $session) {
            return;
        }

        // A session canceled after this job was queued must not announce itself or remind anyone.
        if ($this->event !== 'canceled' && $session->status !== 'scheduled') {
            return;
        }

        $notify->execute($session, $this->event);
    }
}
