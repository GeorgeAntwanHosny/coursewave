<?php

namespace Tests\Feature\LiveSessions;

use App\Actions\Learning\CancelLiveSessionAction;
use App\Actions\Learning\NotifyLiveSessionAudienceAction;
use App\Actions\Learning\ScheduleLiveSessionAction;
use App\Actions\Learning\UpdateLiveSessionAction;
use App\Jobs\NotifyLiveSessionAudienceJob;
use App\Models\LiveSession;
use App\Notifications\LiveSessionNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Illuminate\Validation\ValidationException;
use Tests\Concerns\BuildsLiveSessionFixtures;
use Tests\TestCase;

class LiveSessionActionsTest extends TestCase
{
    use BuildsLiveSessionFixtures, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
        Queue::fake();
        Notification::fake();
        config(['coursewave.live_sessions.reminder_minutes_before' => 30]);
    }

    protected function validData(int $courseId, array $overrides = []): array
    {
        return array_merge([
            'course_id' => $courseId,
            'title' => 'Live Q&A',
            'description' => 'Bring your questions.',
            'starts_at' => now()->addDays(2)->startOfMinute()->toDateTimeString(),
            'duration_minutes' => 60,
            'meeting_url' => 'https://meet.example.com/abc-defg-hij',
        ], $overrides);
    }

    protected function assertInvalid(callable $callback, string $field): void
    {
        try {
            $callback();
            $this->fail("Expected a validation error on [{$field}].");
        } catch (ValidationException $e) {
            $this->assertArrayHasKey($field, $e->errors());
        }
    }

    protected function pushedEvents(): array
    {
        return Queue::pushedJobs()[NotifyLiveSessionAudienceJob::class] ?? [];
    }

    // ---------------------------------------------------------------- scheduling

    public function test_an_instructor_schedules_a_session_for_their_published_course(): void
    {
        $instructor = $this->userWithRole('instructor');
        $course = $this->publishedCourse($instructor);

        $session = app(ScheduleLiveSessionAction::class)->execute($instructor, $this->validData($course->id, [
            'title' => '  Spaces trimmed  ',
        ]));

        $this->assertSame('Spaces trimmed', $session->title);
        $this->assertSame('scheduled', $session->status);
        $this->assertTrue($session->ends_at->equalTo($session->starts_at->copy()->addMinutes(60)));
        $this->assertNull($session->reminder_sent_at); // 2 days away: the reminder is still to come

        Queue::assertPushed(
            NotifyLiveSessionAudienceJob::class,
            fn ($job) => $job->sessionId === $session->id && $job->event === 'scheduled'
        );
    }

    public function test_a_session_starting_inside_the_reminder_window_skips_the_reminder(): void
    {
        $instructor = $this->userWithRole('instructor');
        $course = $this->publishedCourse($instructor);

        $session = app(ScheduleLiveSessionAction::class)->execute($instructor, $this->validData($course->id, [
            'starts_at' => now()->addMinutes(20)->toDateTimeString(),
        ]));

        $this->assertNotNull($session->reminder_sent_at);
    }

    public function test_an_instructor_cannot_schedule_for_someone_elses_course(): void
    {
        $instructor = $this->userWithRole('instructor');
        $foreignCourse = $this->publishedCourse(); // belongs to a different instructor

        $this->assertInvalid(
            fn () => app(ScheduleLiveSessionAction::class)->execute($instructor, $this->validData($foreignCourse->id)),
            'course_id'
        );
        $this->assertSame(0, LiveSession::count());
        Queue::assertNothingPushed();
    }

    public function test_a_draft_course_cannot_have_sessions(): void
    {
        $instructor = $this->userWithRole('instructor');
        $draft = $this->publishedCourse($instructor, status: 'draft');

        $this->assertInvalid(
            fn () => app(ScheduleLiveSessionAction::class)->execute($instructor, $this->validData($draft->id)),
            'course_id'
        );
    }

    public function test_invalid_input_is_rejected_and_nothing_is_stored_or_sent(): void
    {
        $instructor = $this->userWithRole('instructor');
        $course = $this->publishedCourse($instructor);
        $schedule = fn (array $overrides) => fn () => app(ScheduleLiveSessionAction::class)
            ->execute($instructor, $this->validData($course->id, $overrides));

        $this->assertInvalid($schedule(['starts_at' => now()->subHour()->toDateTimeString()]), 'starts_at');
        $this->assertInvalid($schedule(['duration_minutes' => 10]), 'duration_minutes');
        $this->assertInvalid($schedule(['duration_minutes' => 600]), 'duration_minutes');
        $this->assertInvalid($schedule(['title' => '']), 'title');
        $this->assertInvalid($schedule(['meeting_url' => 'http://insecure.example.com/room']), 'meeting_url');
        $this->assertInvalid($schedule(['meeting_url' => 'javascript:alert(1)']), 'meeting_url');
        $this->assertInvalid($schedule(['meeting_url' => 'not a url']), 'meeting_url');
        $this->assertInvalid(fn () => app(ScheduleLiveSessionAction::class)->execute($instructor, ['title' => 'x']), 'course_id');

        $this->assertSame(0, LiveSession::count());
        Queue::assertNothingPushed();
    }

    // ---------------------------------------------------------------- editing

    public function test_editing_only_text_fields_sends_no_notification(): void
    {
        $session = $this->sessionFor($this->publishedCourse(), ['starts_at' => now()->addDays(2)->startOfMinute()]);

        $updated = app(UpdateLiveSessionAction::class)->execute($session, ['title' => 'New title']);

        $this->assertSame('New title', $updated->title);
        Queue::assertNothingPushed();
    }

    public function test_resaving_the_same_minute_is_not_a_reschedule(): void
    {
        $session = $this->sessionFor($this->publishedCourse(), ['starts_at' => now()->addDay()->startOfMinute()]);

        // The form has no seconds, but a stray 30 seconds must still count as the same minute.
        app(UpdateLiveSessionAction::class)->execute($session, [
            'starts_at' => $session->starts_at->copy()->addSeconds(30)->toDateTimeString(),
        ]);

        Queue::assertNothingPushed();
        $this->assertTrue($session->fresh()->starts_at->equalTo($session->starts_at));
    }

    public function test_rescheduling_notifies_updates_the_end_and_resets_the_reminder(): void
    {
        $session = $this->sessionFor($this->publishedCourse(), [
            'starts_at' => now()->addDays(2)->startOfMinute(),
            'reminder_sent_at' => now(), // already reminded for the old time
        ]);
        $newStart = now()->addDays(5)->startOfMinute();

        $updated = app(UpdateLiveSessionAction::class)->execute($session, ['starts_at' => $newStart->toDateTimeString()]);

        $this->assertTrue($updated->starts_at->equalTo($newStart));
        $this->assertTrue($updated->ends_at->equalTo($newStart->copy()->addMinutes(60)));
        $this->assertNull($updated->reminder_sent_at);
        Queue::assertPushed(
            NotifyLiveSessionAudienceJob::class,
            fn ($job) => $job->sessionId === $session->id && $job->event === 'rescheduled'
        );
    }

    public function test_changing_the_duration_counts_as_a_reschedule(): void
    {
        $session = $this->sessionFor($this->publishedCourse());

        $updated = app(UpdateLiveSessionAction::class)->execute($session, ['duration_minutes' => 90]);

        $this->assertTrue($updated->ends_at->equalTo($updated->starts_at->copy()->addMinutes(90)));
        Queue::assertPushed(NotifyLiveSessionAudienceJob::class, fn ($job) => $job->event === 'rescheduled');
    }

    public function test_moving_a_session_inside_the_reminder_window_skips_its_reminder(): void
    {
        $session = $this->sessionFor($this->publishedCourse());

        $updated = app(UpdateLiveSessionAction::class)->execute($session, [
            'starts_at' => now()->addMinutes(10)->toDateTimeString(),
        ]);

        $this->assertNotNull($updated->reminder_sent_at);
    }

    public function test_a_new_start_time_must_be_in_the_future_and_the_link_must_stay_https(): void
    {
        $session = $this->sessionFor($this->publishedCourse());
        $update = fn (array $data) => fn () => app(UpdateLiveSessionAction::class)->execute($session, $data);

        $this->assertInvalid($update(['starts_at' => now()->subHour()->toDateTimeString()]), 'starts_at');
        $this->assertInvalid($update(['meeting_url' => 'http://insecure.example.com']), 'meeting_url');
        Queue::assertNothingPushed();
    }

    public function test_a_session_that_is_already_live_can_still_have_its_text_edited(): void
    {
        $session = $this->sessionFor($this->publishedCourse(), [
            'starts_at' => now()->subMinutes(10)->startOfMinute(),
            'duration_minutes' => 60,
        ]);

        $updated = app(UpdateLiveSessionAction::class)->execute($session, ['title' => 'Updated while live']);

        $this->assertSame('Updated while live', $updated->title);
        Queue::assertNothingPushed();
    }

    public function test_canceled_and_ended_sessions_cannot_be_edited(): void
    {
        $course = $this->publishedCourse();
        $canceled = $this->sessionFor($course, ['status' => 'canceled', 'canceled_at' => now()]);
        $ended = $this->sessionFor($course, ['starts_at' => now()->subHours(3), 'duration_minutes' => 60]);

        $this->assertInvalid(fn () => app(UpdateLiveSessionAction::class)->execute($canceled, ['title' => 'x']), 'session');
        $this->assertInvalid(fn () => app(UpdateLiveSessionAction::class)->execute($ended, ['title' => 'x']), 'session');
    }

    // ---------------------------------------------------------------- canceling

    public function test_canceling_notifies_once_and_is_idempotent(): void
    {
        $session = $this->sessionFor($this->publishedCourse());
        $cancel = app(CancelLiveSessionAction::class);

        $this->assertTrue($cancel->execute($session));
        $this->assertFalse($cancel->execute($session->fresh())); // a repeat does nothing

        $fresh = $session->fresh();
        $this->assertSame('canceled', $fresh->status);
        $this->assertNotNull($fresh->canceled_at);
        Queue::assertPushed(NotifyLiveSessionAudienceJob::class, 1);
        Queue::assertPushed(NotifyLiveSessionAudienceJob::class, fn ($job) => $job->event === 'canceled');
    }

    // ---------------------------------------------------------------- who gets told

    public function test_only_active_subscribers_who_started_the_course_are_notified(): void
    {
        $course = $this->publishedCourse();
        $session = $this->sessionFor($course);

        $enrolled = $this->studentTakingCourse($course, 'Enrolled');
        $noSubscription = $this->studentTakingCourse($course, 'No subscription', subscribed: false);
        $canceledSubscription = $this->studentTakingCourse($course, 'Canceled sub', subscribed: false);
        $this->subscribe($canceledSubscription, 'canceled');
        $notStarted = $this->subscribedStudent('Not started');
        $elsewhere = $this->studentTakingCourse($this->publishedCourse(), 'Elsewhere');
        $admin = $this->userWithRole('admin');

        $count = app(NotifyLiveSessionAudienceAction::class)->execute($session, 'scheduled');

        $this->assertSame(1, $count);
        Notification::assertSentTo($enrolled, LiveSessionNotification::class);
        foreach ([$noSubscription, $canceledSubscription, $notStarted, $elsewhere, $course->instructor, $admin] as $user) {
            Notification::assertNotSentTo($user, LiveSessionNotification::class);
        }
        Notification::assertSentTimes(LiveSessionNotification::class, 1);
    }

    public function test_each_event_sends_its_own_kind_and_links_to_the_live_page(): void
    {
        $course = $this->publishedCourse();
        $session = $this->sessionFor($course, ['title' => 'Q&A', 'starts_at' => now()->addMinutes(20)]);
        $student = $this->studentTakingCourse($course);

        $expected = [
            'scheduled' => 'live_session_scheduled',
            'rescheduled' => 'live_session_rescheduled',
            'canceled' => 'live_session_canceled',
            'reminder' => 'live_session_reminder',
        ];

        foreach ($expected as $event => $kind) {
            Notification::fake();

            app(NotifyLiveSessionAudienceAction::class)->execute($session, $event);

            Notification::assertSentTo($student, LiveSessionNotification::class, function ($n) use ($kind) {
                return $n->kind === $kind && $n->url === '/live' && str_contains($n->title, 'Q&A');
            });
        }
    }

    public function test_an_unpublished_course_has_no_audience(): void
    {
        $draft = $this->publishedCourse(status: 'draft');
        $this->studentTakingCourse($draft);
        $session = $this->sessionFor($draft);

        $this->assertSame(0, app(NotifyLiveSessionAudienceAction::class)->execute($session, 'scheduled'));
        Notification::assertNothingSent();
    }

    public function test_an_unknown_event_is_rejected(): void
    {
        $session = $this->sessionFor($this->publishedCourse());

        $this->expectException(\InvalidArgumentException::class);
        app(NotifyLiveSessionAudienceAction::class)->execute($session, 'party');
    }

    // ---------------------------------------------------------------- the queued job

    public function test_the_job_will_not_announce_or_remind_for_a_session_canceled_in_the_meantime(): void
    {
        $course = $this->publishedCourse();
        $student = $this->studentTakingCourse($course);
        $session = $this->sessionFor($course, ['status' => 'canceled', 'canceled_at' => now()]);
        $action = app(NotifyLiveSessionAudienceAction::class);

        (new NotifyLiveSessionAudienceJob($session->id, 'scheduled'))->handle($action);
        (new NotifyLiveSessionAudienceJob($session->id, 'reminder'))->handle($action);
        Notification::assertNothingSent();

        (new NotifyLiveSessionAudienceJob($session->id, 'canceled'))->handle($action);
        Notification::assertSentTo($student, LiveSessionNotification::class, fn ($n) => $n->kind === 'live_session_canceled');
    }

    public function test_the_job_ignores_a_session_that_no_longer_exists_and_never_retries(): void
    {
        (new NotifyLiveSessionAudienceJob(999999, 'reminder'))->handle(app(NotifyLiveSessionAudienceAction::class));

        Notification::assertNothingSent();
        $this->assertSame(1, (new NotifyLiveSessionAudienceJob(1, 'reminder'))->tries); // at-most-once: a retry could double-notify
    }
}
