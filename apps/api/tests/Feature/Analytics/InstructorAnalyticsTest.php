<?php

namespace Tests\Feature\Analytics;

use App\Models\Certificate;
use App\Models\Comment;
use App\Models\ConsumptionEvent;
use App\Models\Course;
use App\Models\Lesson;
use App\Models\LessonProgress;
use App\Models\User;
use App\Services\Analytics\AnalyticsRange;
use App\Services\Analytics\InstructorAnalytics;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Concerns\BuildsLiveSessionFixtures;
use Tests\TestCase;

class InstructorAnalyticsTest extends TestCase
{
    use BuildsLiveSessionFixtures, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
        config(['coursewave.analytics.cache_seconds' => 0]);

        // Frozen mid-month so every date used below lands in an existing ledger partition.
        $this->travelTo(now()->startOfMonth()->addDays(10)->setTime(12, 0));
    }

    protected function range(): AnalyticsRange
    {
        return AnalyticsRange::lastDays(5); // the 7th to the 11th
    }

    protected function ledger(?int $instructorId, string $type, int $cents, $at): void
    {
        DB::table('ledger_entries')->insert([
            'instructor_id' => $instructorId,
            'entry_type' => $type,
            'amount_cents' => $cents,
            'idempotency_key' => 'test:' . Str::uuid(),
            'created_at' => $at,
            'recorded_at' => now(),
        ]);
    }

    protected function engage(User $student, Course $course, int $seconds, $date): void
    {
        $lesson = Lesson::whereHas('module', fn ($q) => $q->where('course_id', $course->id))->firstOrFail();

        ConsumptionEvent::create([
            'subscription_id' => $student->subscriptions()->firstOrFail()->id,
            'student_id' => $student->id,
            'instructor_id' => $course->instructor_id,
            'course_id' => $course->id,
            'lesson_id' => $lesson->id,
            'lesson_type' => 'text',
            'event_date' => $date->toDateString(),
            'engagement_seconds' => $seconds,
            'completed' => false,
        ]);
    }

    public function test_earnings_count_only_this_instructors_accruals_inside_the_range(): void
    {
        $ada = $this->userWithRole('instructor');
        $bob = $this->userWithRole('instructor');

        $this->ledger($ada->id, 'accrual', 1000, now()->subDay());
        $this->ledger($ada->id, 'accrual', 500, now()->subDays(2));
        $this->ledger($ada->id, 'accrual', 9999, now()->subDays(8));  // before the range
        $this->ledger($ada->id, 'payout', -700, now()->subDay());     // a payout is not earnings
        $this->ledger($bob->id, 'accrual', 4000, now()->subDay());    // somebody else's

        $overview = (new InstructorAnalytics($ada->id))->overview($this->range());

        $this->assertSame(1500, $overview['earned_cents']);
        $this->assertSame(4000, (new InstructorAnalytics($bob->id))->overview($this->range())['earned_cents']);
    }

    public function test_the_balance_comes_from_the_projection_and_paid_out_is_shown_positive(): void
    {
        $ada = $this->userWithRole('instructor');
        DB::table('instructor_balances')->insert([
            'instructor_id' => $ada->id, 'owed_cents' => 5000, 'paid_cents' => -1200, 'updated_at' => now(),
        ]);

        $overview = (new InstructorAnalytics($ada->id))->overview($this->range());

        $this->assertSame(5000, $overview['owed_cents']);
        $this->assertSame(1200, $overview['paid_out_cents']);
        $this->assertSame(3800, $overview['outstanding_cents']);
    }

    public function test_an_instructor_with_no_data_gets_zeros_not_errors(): void
    {
        $ada = $this->userWithRole('instructor');

        $overview = (new InstructorAnalytics($ada->id))->overview($this->range());

        foreach ($overview as $value) {
            $this->assertSame(0, $value);
        }
    }

    public function test_the_earnings_series_has_one_entry_per_day_with_zeros_filled_in(): void
    {
        $ada = $this->userWithRole('instructor');
        $this->ledger($ada->id, 'accrual', 1000, now()->subDay());
        $this->ledger($ada->id, 'accrual', 200, now()->subDays(3));

        $series = (new InstructorAnalytics($ada->id))->earningsSeries($this->range());

        $this->assertCount(5, $series);
        $this->assertSame(1000, $series[now()->subDay()->toDateString()]);
        $this->assertSame(200, $series[now()->subDays(3)->toDateString()]);
        $this->assertSame(0, $series[now()->toDateString()]);
        $this->assertSame(1200, array_sum($series));
    }

    public function test_learners_and_engagement_count_distinct_students_in_range_for_this_instructor_only(): void
    {
        $course = $this->publishedCourse();
        $other = $this->publishedCourse();
        $s1 = $this->studentTakingCourse($course, 'S1');
        $s2 = $this->studentTakingCourse($course, 'S2');
        $s3 = $this->studentTakingCourse($other, 'S3');

        $this->engage($s1, $course, 600, now()->subDay());
        $this->engage($s1, $course, 300, now()->subDays(2));  // same student again: counted once
        $this->engage($s2, $course, 1200, now());
        $this->engage($s2, $course, 9000, now()->subDays(8)); // out of range
        $this->engage($s3, $other, 5000, now()->subDay());    // another instructor

        $analytics = new InstructorAnalytics($course->instructor_id);
        $overview = $analytics->overview($this->range());

        $this->assertSame(2, $overview['active_learners']);
        $this->assertSame(2100, $overview['engagement_seconds']);
        $this->assertSame(1200, $analytics->engagementSeries($this->range())[now()->toDateString()]);
    }

    public function test_completed_lessons_certificates_and_comments_are_scoped_to_own_courses_and_the_range(): void
    {
        $course = $this->publishedCourse();
        $other = $this->publishedCourse();
        $lesson = Lesson::factory()->create([
            'module_id' => \App\Models\Module::factory()->create(['course_id' => $course->id])->id,
            'type' => 'text', 'body' => 'x',
        ]);
        $otherLesson = Lesson::factory()->create([
            'module_id' => \App\Models\Module::factory()->create(['course_id' => $other->id])->id,
            'type' => 'text', 'body' => 'x',
        ]);
        [$a, $b, $c] = [$this->subscribedStudent('A'), $this->subscribedStudent('B'), $this->subscribedStudent('C')];

        $progress = fn ($student, $lessonModel, $courseModel, $completedAt) => LessonProgress::create([
            'student_id' => $student->id, 'lesson_id' => $lessonModel->id, 'course_id' => $courseModel->id,
            'status' => 'completed', 'watched_seconds' => 0, 'completed_at' => $completedAt,
        ]);
        $progress($a, $lesson, $course, now()->subDay());        // counts
        $progress($b, $lesson, $course, now()->subDays(2));      // counts
        $progress($c, $lesson, $course, now()->subDays(8));      // out of range
        $progress($a, $otherLesson, $other, now()->subDay());    // not this instructor's

        $certificate = fn ($student, $courseModel, $issuedAt) => Certificate::create([
            'student_id' => $student->id, 'course_id' => $courseModel->id,
            'certificate_number' => 'CW-' . strtoupper(Str::random(4)) . '-' . strtoupper(Str::random(4)) . '-' . strtoupper(Str::random(4)),
            'student_name' => $student->name, 'course_title' => $courseModel->title,
            'instructor_name' => 'x', 'issued_at' => $issuedAt,
        ]);
        $certificate($a, $course, now()->subDay());              // counts
        $certificate($c, $course, now()->subDays(8));            // out of range
        $certificate($a, $other, now()->subDay());               // not this instructor's

        $comment = fn ($user, $lessonModel, $createdAt = null) => tap(Comment::create([
            'commentable_type' => Lesson::class, 'commentable_id' => $lessonModel->id,
            'user_id' => $user->id, 'body' => 'hello',
        ]), fn ($c) => $createdAt && DB::table('comments')->where('id', $c->id)->update(['created_at' => $createdAt]));
        $comment($a, $lesson);                                   // counts
        $comment($b, $lesson);                                   // counts
        $comment($course->instructor, $lesson);                  // the instructor's own comment
        $comment($c, $lesson, now()->subDays(8));                // out of range
        $comment($a, $otherLesson);                              // not this instructor's
        $comment($c, $lesson)->delete();                         // deleted

        $overview = (new InstructorAnalytics($course->instructor_id))->overview($this->range());

        $this->assertSame(2, $overview['lessons_completed']);
        $this->assertSame(1, $overview['certificates']);
        $this->assertSame(2, $overview['new_comments']);
    }

    public function test_only_upcoming_scheduled_sessions_of_own_courses_are_counted(): void
    {
        $course = $this->publishedCourse();
        $other = $this->publishedCourse();

        $this->sessionFor($course, ['starts_at' => now()->addDay()]);
        $this->sessionFor($course, ['starts_at' => now()->addDays(2), 'status' => 'canceled', 'canceled_at' => now()]);
        $this->sessionFor($course, ['starts_at' => now()->subDays(2), 'duration_minutes' => 60]);
        $this->sessionFor($other, ['starts_at' => now()->addDay()]);

        $overview = (new InstructorAnalytics($course->instructor_id))->overview($this->range());

        $this->assertSame(1, $overview['upcoming_live_sessions']);
    }

    public function test_results_are_cached_per_instructor_and_never_shared(): void
    {
        config(['coursewave.analytics.cache_seconds' => 300]);
        $ada = $this->userWithRole('instructor');
        $bob = $this->userWithRole('instructor');
        $this->ledger($ada->id, 'accrual', 1000, now()->subDay());

        $this->assertSame(1000, (new InstructorAnalytics($ada->id))->overview($this->range())['earned_cents']);

        $this->ledger($ada->id, 'accrual', 500, now()->subDay());
        $this->assertSame(1000, (new InstructorAnalytics($ada->id))->overview($this->range())['earned_cents']); // still cached
        $this->assertSame(0, (new InstructorAnalytics($bob->id))->overview($this->range())['earned_cents']);    // a different key

        config(['coursewave.analytics.cache_seconds' => 0]);
        $this->assertSame(1500, (new InstructorAnalytics($ada->id))->overview($this->range())['earned_cents']); // caching off: fresh
    }
}
