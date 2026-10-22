<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Notifications\SystemTestNotification;
use Illuminate\Console\Command;

class SendTestNotification extends Command
{
    protected $signature = 'notifications:test {email : Email of the user to notify}';
    protected $description = 'LOCAL/TESTING ONLY. Sends a test notification so the live bell can be checked without triggering a real flow.';

    public function handle(): int
    {
        if (! app()->environment(['local', 'testing'])) {
            $this->error('This command only runs when APP_ENV is local or testing.');

            return self::FAILURE;
        }

        $user = User::where('email', $this->argument('email'))->first();

        if (! $user) {
            $this->error('No user with that email.');

            return self::FAILURE;
        }

        // Sent directly (not through SafeNotify) so a broken queue or broadcaster is visible here.
        $user->notify(SystemTestNotification::make('Test notification', 'If you can read this live, Reverb is working.'));

        $this->info("Test notification sent to {$user->email}. Make sure `queue:work` and `reverb:start` are running.");

        return self::SUCCESS;
    }
}
