<?php

namespace Tests\Feature\LiveSessions;

use App\Models\LiveSession;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\BuildsLiveSessionFixtures;
use Tests\TestCase;

class LiveSessionApiTest extends TestCase
{
    use BuildsLiveSessionFixtures, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
        config(['coursewave.live_sessions.join_opens_minutes_before' => 15]);
    }

    public function test_guests_are_unauthenticated(): void
    {
        $this->getJson('/api/learning/live-sessions')->assertStatus(401);
    }

    public function test_a_student_without_a_subscription_is_blocked(): void
    {
        $student = $this->userWithRole('student');

        $this->actingWith($this->tokenFor($student))->getJson('/api/learning/live-sessions')
            ->assertStatus(403)
            ->assertJsonPath('success', false);
    }

    public function test_the_list_contains_only_sessions_of_courses_the_student_has_started(): void
    {
        $started = $this->publishedCourse();
        $notStarted = $this->publishedCourse();
        $student = $this->studentTakingCourse($started);
        $this->sessionFor($started, ['title' => 'Mine']);
        $this->sessionFor($notStarted, ['title' => 'Not mine']);

        $this->actingWith($this->tokenFor($student))->getJson('/api/learning/live-sessions')
            ->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonCount(1, 'data.items')
            ->assertJsonPath('data.items.0.title', 'Mine');
    }

    public function test_canceled_ended_and_unpublished_sessions_are_never_listed(): void
    {
        $course = $this->publishedCourse();
        $draft = $this->publishedCourse(status: 'draft');
        $student = $this->studentTakingCourse($course);
        $this->studentProgressFor($student, $draft);

        $this->sessionFor($course, ['title' => 'Canceled', 'status' => 'canceled', 'canceled_at' => now()]);
        $this->sessionFor($course, ['title' => 'Ended', 'starts_at' => now()->subHours(3), 'duration_minutes' => 60]);
        $this->sessionFor($draft, ['title' => 'Draft course']);
        $this->sessionFor($course, ['title' => 'Valid']);

        $this->actingWith($this->tokenFor($student))->getJson('/api/learning/live-sessions')
            ->assertJsonCount(1, 'data.items')
            ->assertJsonPath('data.items.0.title', 'Valid');
    }

    public function test_sessions_are_ordered_by_start_time(): void
    {
        $course = $this->publishedCourse();
        $student = $this->studentTakingCourse($course);
        $this->sessionFor($course, ['title' => 'Later', 'starts_at' => now()->addDays(3)]);
        $this->sessionFor($course, ['title' => 'Sooner', 'starts_at' => now()->addDay()]);

        $this->actingWith($this->tokenFor($student))->getJson('/api/learning/live-sessions')
            ->assertJsonPath('data.items.0.title', 'Sooner')
            ->assertJsonPath('data.items.1.title', 'Later');
    }

    public function test_the_join_link_stays_hidden_until_the_join_window_opens(): void
    {
        $course = $this->publishedCourse();
        $student = $this->studentTakingCourse($course);
        $this->sessionFor($course, ['title' => 'Far', 'starts_at' => now()->addHours(2)]);
        $this->sessionFor($course, ['title' => 'Near', 'starts_at' => now()->addMinutes(10), 'meeting_url' => 'https://meet.example.com/near']);

        $response = $this->actingWith($this->tokenFor($student))->getJson('/api/learning/live-sessions')
            ->assertStatus(200)
            ->assertJsonPath('data.items.0.title', 'Near')
            ->assertJsonPath('data.items.0.can_join', true)
            ->assertJsonPath('data.items.0.join_url', 'https://meet.example.com/near')
            ->assertJsonPath('data.items.0.status', 'upcoming')
            ->assertJsonPath('data.items.1.title', 'Far')
            ->assertJsonPath('data.items.1.can_join', false)
            ->assertJsonPath('data.items.1.join_url', null);

        // The far session's link must not appear anywhere in the payload.
        $this->assertStringNotContainsString($this->sessionLink('Far'), $response->getContent());
    }

    public function test_a_session_in_progress_is_live_and_joinable(): void
    {
        $course = $this->publishedCourse();
        $student = $this->studentTakingCourse($course);
        $this->sessionFor($course, ['starts_at' => now()->subMinutes(10), 'duration_minutes' => 60]);

        $this->actingWith($this->tokenFor($student))->getJson('/api/learning/live-sessions')
            ->assertJsonPath('data.items.0.status', 'live')
            ->assertJsonPath('data.items.0.can_join', true);
    }

    public function test_a_non_https_link_is_never_sent_and_the_raw_url_key_never_appears(): void
    {
        $course = $this->publishedCourse();
        $student = $this->studentTakingCourse($course);
        $session = $this->sessionFor($course, ['starts_at' => now()->addMinutes(5)]);

        // Bypass validation, as a bad import or a manual DB edit would.
        DB::table('live_sessions')->where('id', $session->id)->update(['meeting_url' => 'javascript:alert(1)']);

        $response = $this->actingWith($this->tokenFor($student))->getJson('/api/learning/live-sessions')
            ->assertJsonPath('data.items.0.can_join', false)
            ->assertJsonPath('data.items.0.join_url', null);

        $this->assertStringNotContainsString('javascript:', $response->getContent());
        $this->assertArrayNotHasKey('meeting_url', $response->json('data.items.0'));
    }

    public function test_the_course_endpoint_lists_sessions_for_any_subscriber_and_hides_unpublished_courses(): void
    {
        $course = $this->publishedCourse();
        $draft = $this->publishedCourse(status: 'draft');
        $subscriber = $this->subscribedStudent(); // has not started the course
        $this->sessionFor($course, ['title' => 'Open to all subscribers']);
        $this->sessionFor($draft);
        $token = $this->tokenFor($subscriber);

        $this->actingWith($token)->getJson("/api/learning/courses/{$course->slug}/live-sessions")
            ->assertStatus(200)
            ->assertJsonCount(1, 'data.items')
            ->assertJsonPath('data.items.0.title', 'Open to all subscribers');

        $this->actingWith($token)->getJson("/api/learning/courses/{$draft->slug}/live-sessions")->assertStatus(404);
        $this->actingWith($token)->getJson('/api/learning/courses/does-not-exist/live-sessions')->assertStatus(404);
    }

    protected function studentProgressFor($student, $course): void
    {
        $module = \App\Models\Module::factory()->create(['course_id' => $course->id]);
        $lesson = \App\Models\Lesson::factory()->create(['module_id' => $module->id, 'type' => 'text', 'body' => 'short']);
        \App\Models\LessonProgress::create([
            'student_id' => $student->id, 'lesson_id' => $lesson->id, 'course_id' => $course->id,
            'status' => 'in_progress', 'watched_seconds' => 0,
        ]);
    }

    protected function sessionLink(string $title): string
    {
        return LiveSession::where('title', $title)->value('meeting_url');
    }
}
