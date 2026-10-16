<?php

namespace Tests\Feature\Billing;

use App\Actions\Billing\CancelSubscriptionAction;
use App\Actions\Billing\GenerateRecognitionSlicesAction;
use App\Actions\Billing\RecognizeSubscriptionRevenueAction;
use App\Models\ConsumptionEvent;
use App\Models\Course;
use App\Models\InstructorBalance;
use App\Models\LedgerEntry;
use App\Models\Lesson;
use App\Models\Module;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use App\Support\LargestRemainderSplitter;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class RevenueRecognitionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Role::firstOrCreate(['name' => 'instructor', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'student', 'guard_name' => 'web']);
    }

    /**
     * Builds a subscription whose term started $startedDaysAgo days ago
     * (defaults to the full term length, i.e. the term ends today).
     * Slices are generated exactly as production does.
     */
    protected function makeSubscription(int $termDays, int $totalCents, ?int $startedDaysAgo = null): array
    {
        $startedDaysAgo ??= $termDays;

        $instructor = User::factory()->create();
        $instructor->assignRole('instructor');
        $student = User::factory()->create();
        $student->assignRole('student');
        $course = Course::factory()->create(['instructor_id' => $instructor->id, 'status' => 'published']);
        $module = Module::factory()->create(['course_id' => $course->id]);
        $lesson = Lesson::factory()->create(['module_id' => $module->id, 'type' => 'video']);
        $plan = Plan::factory()->create(['term_days' => $termDays, 'price_cents' => $totalCents]);

        $subscription = Subscription::factory()->create([
            'student_id' => $student->id,
            'plan_id' => $plan->id,
            'status' => 'active',
            'term_days' => $termDays,
            'total_amount_cents' => $totalCents,
            'starts_at' => now()->subDays($startedDaysAgo)->toDateString(),
            'ends_at' => now()->subDays($startedDaysAgo)->addDays($termDays)->toDateString(),
        ]);

        (new GenerateRecognitionSlicesAction())->execute($subscription->fresh());

        return compact('instructor', 'student', 'course', 'lesson', 'subscription');
    }

    protected function logConsumption($subscription, $instructor, $course, $lesson, string $date, int $seconds): void
    {
        ConsumptionEvent::create([
            'subscription_id' => $subscription->id,
            'student_id' => $subscription->student_id,
            'instructor_id' => $instructor->id,
            'course_id' => $course->id,
            'lesson_id' => $lesson->id,
            'lesson_type' => 'video',
            'event_date' => $date,
            'engagement_seconds' => $seconds,
            'completed' => false,
        ]);
    }

    /**
     * An as-of date under which exactly the first $daysElapsed slices of the
     * subscription count as elapsed (slice_date < asOf). Deterministic and
     * independent of today's date, so tests control precisely which slices
     * are eligible and whether the term-end sweep can trigger.
     */
    protected function asOfAfterDay(Subscription $subscription, int $daysElapsed): Carbon
    {
        return Carbon::parse($subscription->fresh()->starts_at)->addDays($daysElapsed);
    }

    public function test_largest_remainder_splitter_distributes_remainder_correctly(): void
    {
        // 100 cents / 3 equal weights → 33 each, 1 leftover cent goes to the
        // lowest instructor_id on a tie.
        $result = LargestRemainderSplitter::split(100, [3 => 1, 1 => 1, 2 => 1]);

        $this->assertEquals(100, array_sum($result));
        $this->assertEquals(34, $result[1]);
        $this->assertEquals(33, $result[2]);
        $this->assertEquals(33, $result[3]);
    }

    public function test_largest_remainder_splitter_handles_exact_and_uneven_splits(): void
    {
        $exact = LargestRemainderSplitter::split(10, [1 => 5, 2 => 3, 3 => 2]);
        $this->assertEquals([1 => 5, 2 => 3, 3 => 2], $exact);

        $uneven = LargestRemainderSplitter::split(10, [1 => 1, 2 => 1, 3 => 1]);

        $this->assertEquals(10, array_sum($uneven));
    }

    public function test_slice_with_consumption_recognizes_with_platform_fee_and_accrual(): void
    {
        config(['services.platform.fee_percent' => 20]);
        ['instructor' => $instructor, 'course' => $course, 'lesson' => $lesson, 'subscription' => $subscription]
            = $this->makeSubscription(3, 300);

        $day1 = Carbon::parse($subscription->starts_at)->toDateString();
        $this->logConsumption($subscription, $instructor, $course, $lesson, $day1, 60);

        // Only day 1 has elapsed.
        (new RecognizeSubscriptionRevenueAction())->execute($subscription->fresh(), $this->asOfAfterDay($subscription, 1));

        $slice = $subscription->recognitionSlices()->where('slice_date', $day1)->first();
        $this->assertEquals('recognized', $slice->status);

        $feeEntry = LedgerEntry::where('entry_type', 'platform_fee')->where('recognition_slice_id', $slice->id)->first();
        $this->assertEquals(20, $feeEntry->amount_cents); // 20% of 100

        $accrualEntry = LedgerEntry::where('entry_type', 'accrual')->where('instructor_id', $instructor->id)->first();
        $this->assertEquals(80, $accrualEntry->amount_cents);

        $this->assertEquals(80, InstructorBalance::find($instructor->id)->owed_cents);

        // Days 2 and 3 are untouched.
        $this->assertEquals(2, $subscription->recognitionSlices()->where('status', 'pending')->count());
    }

    public function test_slice_with_zero_consumption_stays_pending(): void
    {
        ['subscription' => $subscription] = $this->makeSubscription(3, 300);

        // Day 1 elapsed, term not over, no consumption anywhere.
        (new RecognizeSubscriptionRevenueAction())->execute($subscription->fresh(), $this->asOfAfterDay($subscription, 1));

        $firstSlice = $subscription->recognitionSlices()->orderBy('slice_date')->first();
        $this->assertEquals('pending', $firstSlice->status);
        $this->assertEquals(0, LedgerEntry::count());
    }

    public function test_zero_consumption_backlog_is_swept_by_later_day_using_that_days_weights(): void
    {
        config(['services.platform.fee_percent' => 0]); // isolate the split from fee math
        ['instructor' => $instructor, 'course' => $course, 'lesson' => $lesson, 'subscription' => $subscription]
            = $this->makeSubscription(3, 300);

        $day1 = Carbon::parse($subscription->starts_at)->toDateString();
        $day2 = Carbon::parse($subscription->starts_at)->addDay()->toDateString();

        // day1: no activity. day2: activity.
        $this->logConsumption($subscription, $instructor, $course, $lesson, $day2, 50);

        // Days 1 and 2 have elapsed; the term itself has not ended.
        (new RecognizeSubscriptionRevenueAction())->execute($subscription->fresh(), $this->asOfAfterDay($subscription, 2));

        $slice1 = $subscription->recognitionSlices()->where('slice_date', $day1)->first();
        $slice2 = $subscription->recognitionSlices()->where('slice_date', $day2)->first();

        $this->assertEquals('recognized', $slice1->status);
        $this->assertEquals('recognized', $slice2->status);

        $this->assertEquals(100, LedgerEntry::where('recognition_slice_id', $slice1->id)->where('entry_type', 'accrual')->value('amount_cents'));
        $this->assertEquals(100, LedgerEntry::where('recognition_slice_id', $slice2->id)->where('entry_type', 'accrual')->value('amount_cents'));

        // Day 3 is untouched.
        $this->assertEquals(1, $subscription->recognitionSlices()->where('status', 'pending')->count());
    }

    public function test_term_end_sweep_uses_cumulative_consumption_share(): void
    {
        config(['services.platform.fee_percent' => 0]);
        $instructorA = User::factory()->create();
        $instructorA->assignRole('instructor');
        $instructorB = User::factory()->create();
        $instructorB->assignRole('instructor');
        $student = User::factory()->create();
        $student->assignRole('student');

        $courseA = Course::factory()->create(['instructor_id' => $instructorA->id, 'status' => 'published']);
        $courseB = Course::factory()->create(['instructor_id' => $instructorB->id, 'status' => 'published']);
        $moduleA = Module::factory()->create(['course_id' => $courseA->id]);
        $moduleB = Module::factory()->create(['course_id' => $courseB->id]);
        $lessonA = Lesson::factory()->create(['module_id' => $moduleA->id, 'type' => 'video']);
        $lessonB = Lesson::factory()->create(['module_id' => $moduleB->id, 'type' => 'video']);

        $plan = Plan::factory()->create(['term_days' => 2, 'price_cents' => 200]);
        $subscription = Subscription::factory()->create([
            'student_id' => $student->id,
            'plan_id' => $plan->id,
            'status' => 'active',
            'term_days' => 2,
            'total_amount_cents' => 200,
            'starts_at' => now()->subDays(2)->toDateString(),
            'ends_at' => now()->subDay()->toDateString(), // term already ended
        ]);
        (new GenerateRecognitionSlicesAction())->execute($subscription->fresh());

        $day1 = Carbon::parse($subscription->starts_at)->toDateString();
        $day2 = Carbon::parse($subscription->starts_at)->addDay()->toDateString();

        // Day 1 has no activity; day 2 has A=30, B=10. Day 1's slice can only
        // be resolved by the term-end sweep using cumulative weights.
        $this->logConsumption($subscription, $instructorA, $courseA, $lessonA, $day2, 30);
        $this->logConsumption($subscription, $instructorB, $courseB, $lessonB, $day2, 10);

        (new RecognizeSubscriptionRevenueAction())->execute($subscription->fresh(), now());

        $slice1 = $subscription->recognitionSlices()->where('slice_date', $day1)->first();
        $this->assertEquals('recognized', $slice1->status);

        // 30:10 → 75% / 25% of 100 cents
        $this->assertEquals(75, LedgerEntry::where('recognition_slice_id', $slice1->id)->where('instructor_id', $instructorA->id)->value('amount_cents'));
        $this->assertEquals(25, LedgerEntry::where('recognition_slice_id', $slice1->id)->where('instructor_id', $instructorB->id)->value('amount_cents'));
    }

    public function test_term_end_sweep_with_zero_engagement_ever_becomes_full_platform_revenue(): void
    {
        config(['services.platform.fee_percent' => 20]);
        $plan = Plan::factory()->create(['term_days' => 2, 'price_cents' => 200]);
        $student = User::factory()->create();
        $student->assignRole('student');

        $subscription = Subscription::factory()->create([
            'student_id' => $student->id,
            'plan_id' => $plan->id,
            'status' => 'active',
            'term_days' => 2,
            'total_amount_cents' => 200,
            'starts_at' => now()->subDays(2)->toDateString(),
            'ends_at' => now()->subDay()->toDateString(),
        ]);
        (new GenerateRecognitionSlicesAction())->execute($subscription->fresh());

        (new RecognizeSubscriptionRevenueAction())->execute($subscription->fresh(), now());

        $slices = $subscription->recognitionSlices()->get();
        $this->assertTrue($slices->every(fn ($s) => $s->status === 'recognized'));

        // The FULL slice amounts (not just the 20% fee) become platform revenue.
        $this->assertEquals(0, LedgerEntry::where('entry_type', 'accrual')->count());
        $this->assertEquals(200, LedgerEntry::where('entry_type', 'platform_fee')->sum('amount_cents'));
    }

    public function test_running_recognition_twice_does_not_double_credit(): void
    {
        config(['services.platform.fee_percent' => 20]);
        ['instructor' => $instructor, 'course' => $course, 'lesson' => $lesson, 'subscription' => $subscription]
            = $this->makeSubscription(3, 300);

        $day1 = Carbon::parse($subscription->starts_at)->toDateString();
        $this->logConsumption($subscription, $instructor, $course, $lesson, $day1, 60);

        $asOf = $this->asOfAfterDay($subscription, 1);
        $action = new RecognizeSubscriptionRevenueAction();
        $action->execute($subscription->fresh(), $asOf);
        $action->execute($subscription->fresh(), $asOf); // same range again

        $this->assertEquals(1, LedgerEntry::where('entry_type', 'accrual')->count());
        $this->assertEquals(1, LedgerEntry::where('entry_type', 'platform_fee')->count());
        $this->assertEquals(80, InstructorBalance::find($instructor->id)->owed_cents); // not 160
    }

    public function test_rebuild_balances_command_matches_ledger_sum(): void
    {
        config(['services.platform.fee_percent' => 20]);
        ['instructor' => $instructor, 'course' => $course, 'lesson' => $lesson, 'subscription' => $subscription]
            = $this->makeSubscription(3, 300);

        $day1 = Carbon::parse($subscription->starts_at)->toDateString();
        $this->logConsumption($subscription, $instructor, $course, $lesson, $day1, 60);
        (new RecognizeSubscriptionRevenueAction())->execute($subscription->fresh(), $this->asOfAfterDay($subscription, 1));

        // Simulate projection drift.
        DB::table('instructor_balances')->where('instructor_id', $instructor->id)->update(['owed_cents' => 99999]);

        $this->artisan('ledger:rebuild-balances')->assertExitCode(0);

        $this->assertEquals(80, InstructorBalance::find($instructor->id)->owed_cents);
    }

    public function test_cancel_subscription_does_not_void_elapsed_unrecognized_slices(): void
    {
        \Illuminate\Support\Facades\Queue::fake(); // cancellation now queues a refund; this test never talks to a gateway

        // 5-day term that started 2 days ago: slices on today-2 and today-1
        // are elapsed but still pending (recognition hasn't caught up);
        // today, today+1, today+2 are not yet elapsed.
        ['subscription' => $subscription] = $this->makeSubscription(5, 500, startedDaysAgo: 2);

        (new CancelSubscriptionAction())->execute($subscription->fresh());

        $elapsed = $subscription->recognitionSlices()->where('slice_date', '<', now()->toDateString())->get();
        $this->assertCount(2, $elapsed);
        $this->assertTrue($elapsed->every(fn ($s) => $s->status === 'pending'));

        $future = $subscription->recognitionSlices()->where('slice_date', '>=', now()->toDateString())->get();
        $this->assertCount(3, $future);
        $this->assertTrue($future->every(fn ($s) => $s->status === 'voided'));
    }
}
