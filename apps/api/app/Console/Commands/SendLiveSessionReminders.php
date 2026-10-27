<?php

namespace App\Console\Commands;

use App\Jobs\NotifyLiveSessionAudienceJob;
use App\Models\LiveSession;
use Illuminate\Console\Command;

class SendLiveSessionReminders extends Command
{
    protected $signature = 'live-sessions:send-reminders';
    protected $description = 'Remind students once about live sessions starting soon. Safe to run every minute and from several servers.';

    public function handle(): int
    {
        $window = LiveSession::reminderWindowMinutes();
        $sent = 0;

        LiveSession::query()
            ->where('status', 'scheduled')
            ->whereNull('reminder_sent_at')
            ->where('starts_at', '>', now())                       // never remind about a session that already started
            ->where('starts_at', '<=', now()->addMinutes($window))
            ->each(function (LiveSession $session) use (&$sent) {
                // Atomic claim: only the run whose UPDATE actually changed the row sends the reminder.
                $claimed = LiveSession::whereKey($session->id)
                    ->where('status', 'scheduled')
                    ->whereNull('reminder_sent_at')
                    ->update(['reminder_sent_at' => now()]);

                if ($claimed) {
                    NotifyLiveSessionAudienceJob::dispatch($session->id, 'reminder');
                    $sent++;
                }
            });

        $this->info("Queued {$sent} live session reminder(s).");

        return self::SUCCESS;
    }
}
