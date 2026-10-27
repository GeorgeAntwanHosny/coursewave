<?php

namespace Tests\Feature\LiveSessions;

use App\Actions\Learning\UpdateLiveSessionAction;
use App\Jobs\NotifyLiveSessionAudienceJob;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\Concerns\BuildsLiveSessionFixtures;
use Tests\TestCase;

class LiveSessionRemindersTest extends TestCase
{
    use BuildsLiveSessionFixtures, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
        Queue::fake();
        config(['coursewave.live_sessions.reminder_minutes_before' => 30]);
    }

    protected function runReminders(): void
    {
        $this->artisan('live-sessions:send-reminders')->assertExitCode(0);
    }

    public function test_a_session_starting_soon_gets_exactly_one_reminder(): void
    {
        $session = $this->sessionFor($this->publishedCourse(), ['starts_at' => now()->addMinutes(20)]);

        $this->runReminders();
        $this->runReminders(); // a second run, or a second server, must not remind again

        Queue::assertPushed(NotifyLiveSessionAudienceJob::class, 1);
        Queue::assertPushed(
            NotifyLiveSessionAudienceJob::class,
            fn ($job) => $job->sessionId === $session->id && $job->event === 'reminder'
        );
        $this->assertNotNull($session->fresh()->reminder_sent_at);
    }

    public function test_sessions_outside_the_window_or_not_eligible_are_left_alone(): void
    {
        $course = $this->publishedCourse();
        $this->sessionFor($course, ['starts_at' => now()->addHours(2)]);                                   // too far away
        $this->sessionFor($course, ['starts_at' => now()->subMinutes(5), 'duration_minutes' => 60]);       // already started
        $this->sessionFor($course, ['starts_at' => now()->addMinutes(10), 'status' => 'canceled', 'canceled_at' => now()]);
        $this->sessionFor($course, ['starts_at' => now()->addMinutes(10), 'reminder_sent_at' => now()]);  // already handled

        $this->runReminders();

        Queue::assertNothingPushed();
    }

    public function test_rescheduling_resets_the_reminder_so_it_fires_for_the_new_time(): void
    {
        $session = $this->sessionFor($this->publishedCourse(), [
            'starts_at' => now()->addMinutes(20),
            'reminder_sent_at' => now(), // reminded for the old time
        ]);

        app(UpdateLiveSessionAction::class)->execute($session, ['starts_at' => now()->addHours(3)->toDateTimeString()]);
        $this->assertNull($session->fresh()->reminder_sent_at);

        $this->runReminders();
        Queue::assertNotPushed(NotifyLiveSessionAudienceJob::class, fn ($job) => $job->event === 'reminder');

        // Time passes until the new start is 20 minutes away: now it is reminded.
        $this->travel(160)->minutes();
        $this->runReminders();

        Queue::assertPushed(NotifyLiveSessionAudienceJob::class, fn ($job) => $job->event === 'reminder');
    }
}
