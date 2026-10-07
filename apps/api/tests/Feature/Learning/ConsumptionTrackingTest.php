<?php

namespace Tests\Feature\Learning;

use App\Models\ConsumptionEvent;
use App\Models\Course;
use App\Models\Lesson;
use App\Models\LessonProgress;
use App\Models\Module;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ConsumptionTrackingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Role::firstOrCreate(['name' => 'student', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'instructor', 'guard_name' => 'web']);
    }

    protected function makePublishedLesson(string $type = 'text'): Lesson
    {
        $instructor = User::factory()->create();
        $instructor->assignRole('instructor');
        $course = Course::factory()->create(['instructor_id' => $instructor->id, 'status' => 'published']);
        $module = Module::factory()->create(['course_id' => $course->id]);

        return Lesson::factory()->create([
            'module_id' => $module->id,
            'type' => $type,
            'body' => $type === 'text' ? str_repeat('word ', 400) : null, // ~400 words → non-zero estimated reading time
            'video_url' => $type === 'video' ? 'https://example.com/video.mp4' : null,
        ]);
    }

    protected function studentWithActiveSubscription(): array
    {
        $student = User::factory()->create();
        $student->assignRole('student');
        $plan = Plan::factory()->create();
        Subscription::factory()->create([
            'student_id' => $student->id,
            'plan_id' => $plan->id,
            'status' => 'active',
        ]);
        $token = $student->createToken('test')->plainTextToken;

        return [$student, $token];
    }

    public function test_guest_cannot_access_learning_endpoints(): void
    {
        $lesson = $this->makePublishedLesson();

        $this->getJson("/api/learning/lessons/{$lesson->id}")->assertStatus(401);
    }

    public function test_student_without_active_subscription_is_blocked(): void
    {
        $student = User::factory()->create();
        $student->assignRole('student');
        $token = $student->createToken('test')->plainTextToken;

        $lesson = $this->makePublishedLesson();

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson("/api/learning/lessons/{$lesson->id}")
            ->assertStatus(403)
            ->assertJsonPath('success', false);
    }

    public function test_subscribed_student_can_fetch_course_tree_with_progress(): void
    {
        [, $token] = $this->studentWithActiveSubscription();
        $lesson = $this->makePublishedLesson();
        $course = $lesson->module->course;

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson("/api/learning/courses/{$course->slug}")
            ->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.completion_percent', 0)
            ->assertJsonPath('data.course.modules.0.lessons.0.status', 'not_started');
    }

    public function test_subscribed_student_can_fetch_lesson_detail_with_navigation(): void
    {
        [, $token] = $this->studentWithActiveSubscription();
        $lesson = $this->makePublishedLesson();

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson("/api/learning/lessons/{$lesson->id}")
            ->assertStatus(200)
            ->assertJsonPath('data.id', $lesson->id)
            ->assertJsonPath('data.progress.status', 'not_started')
            ->assertJsonPath('data.previous_lesson_id', null)
            ->assertJsonPath('data.next_lesson_id', null);
    }

    public function test_video_heartbeat_records_consumption_event_and_accumulates_progress(): void
    {
        [$student, $token] = $this->studentWithActiveSubscription();
        $lesson = $this->makePublishedLesson('video');

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/learning/lessons/{$lesson->id}/progress", ['watched_seconds' => 20])
            ->assertStatus(200)
            ->assertJsonPath('data.status', 'in_progress');

        $this->assertDatabaseHas('consumption_events', [
            'student_id' => $student->id,
            'lesson_id' => $lesson->id,
            'lesson_type' => 'video',
            'engagement_seconds' => 20,
            'completed' => false,
        ]);

        $progress = LessonProgress::where('student_id', $student->id)->where('lesson_id', $lesson->id)->first();
        $this->assertEquals(20, $progress->watched_seconds);

        // Second heartbeat accumulates, doesn't overwrite.
        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/learning/lessons/{$lesson->id}/progress", ['watched_seconds' => 15]);

        $progress->refresh();
        $this->assertEquals(35, $progress->watched_seconds);
        $this->assertEquals(2, ConsumptionEvent::where('lesson_id', $lesson->id)->count());
    }

    public function test_video_ended_event_marks_lesson_completed(): void
    {
        [$student, $token] = $this->studentWithActiveSubscription();
        $lesson = $this->makePublishedLesson('video');

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/learning/lessons/{$lesson->id}/progress", ['completed' => true]);

        $progress = LessonProgress::where('student_id', $student->id)->where('lesson_id', $lesson->id)->first();
        $this->assertEquals('completed', $progress->status);
        $this->assertNotNull($progress->completed_at);
    }

    public function test_marking_text_lesson_complete_records_estimated_engagement_seconds(): void
    {
        [, $token] = $this->studentWithActiveSubscription();
        $lesson = $this->makePublishedLesson('text');

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/learning/lessons/{$lesson->id}/progress", ['completed' => true])
            ->assertStatus(200)
            ->assertJsonPath('data.status', 'completed');

        $event = ConsumptionEvent::where('lesson_id', $lesson->id)->first();
        $this->assertNotNull($event);
        $this->assertEquals('text', $event->lesson_type);
        $this->assertTrue($event->completed);
        $this->assertGreaterThan(0, $event->engagement_seconds);
    }

    public function test_heartbeat_watched_seconds_over_cap_is_rejected(): void
    {
        [, $token] = $this->studentWithActiveSubscription();
        $lesson = $this->makePublishedLesson('video');

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/learning/lessons/{$lesson->id}/progress", ['watched_seconds' => 99999])
            ->assertStatus(422);
    }

    public function test_course_completion_percent_reflects_completed_lessons(): void
    {
        [, $token] = $this->studentWithActiveSubscription();

        $instructor = User::factory()->create();
        $instructor->assignRole('instructor');
        $course = Course::factory()->create(['instructor_id' => $instructor->id, 'status' => 'published']);
        $module = Module::factory()->create(['course_id' => $course->id]);
        $lessonA = Lesson::factory()->create(['module_id' => $module->id, 'type' => 'text', 'body' => 'short', 'order' => 1]);
        Lesson::factory()->create(['module_id' => $module->id, 'type' => 'text', 'body' => 'short', 'order' => 2]);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/learning/lessons/{$lessonA->id}/progress", ['completed' => true]);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson("/api/learning/courses/{$course->slug}")
            ->assertStatus(200)
            ->assertJsonPath('data.completion_percent', 50);
    }
}
