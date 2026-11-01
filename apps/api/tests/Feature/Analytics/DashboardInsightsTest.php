<?php

namespace Tests\Feature\Analytics;

use App\Models\Comment;
use App\Models\Course;
use App\Models\InstructorPayoutAccount;
use App\Models\Lesson;
use App\Models\Module;
use App\Models\PayoutBatch;
use App\Models\Refund;
use App\Models\Subscription;
use App\Services\Analytics\AnalyticsRange;
use App\Services\Analytics\AttentionItems;
use App\Services\Analytics\InstructorAnalytics;
use App\Support\Trend;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Concerns\BuildsLiveSessionFixtures;
use Tests\TestCase;

class DashboardInsightsTest extends TestCase
{
    use BuildsLiveSessionFixtures, RefreshDatabase;

    private int $periodCounter = 0;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
        config([
            'coursewave.analytics.cache_seconds' => 0,
            'services.payouts.minimum_cents' => 1000,
        ]);
    }

    protected function lessonOf(Course $course): Lesson
    {
        return Lesson::factory()->create([
            'module_id' => Module::factory()->create(['course_id' => $course->id])->id,
            'type' => 'text',
            'body' => 'x',
        ]);
    }

    protected function comment(Lesson $lesson, int $userId, ?int $parentId = null): Comment
    {
        return Comment::create([
            'commentable_type' => Lesson::class,
            'commentable_id' => $lesson->id,
            'user_id' => $userId,
            'parent_id' => $parentId,
            'body' => 'hello',
        ]);
    }

    protected function payoutAccount(int $instructorId, bool $enabled): InstructorPayoutAccount
    {
        return InstructorPayoutAccount::create([
            'instructor_id' => $instructorId,
            'provider' => 'stripe',
            'provider_account_id' => 'acct_' . Str::random(8),
            'provider_account_version' => 'v2',
            'payouts_enabled' => $enabled,
        ]);
    }

    protected function titles(array $items): array
    {
        return array_column($items, 'title');
    }

    protected function payoutBatch(string $status, $completedAt = null): PayoutBatch
    {
        $instructor = $this->userWithRole('instructor');
        $this->periodCounter++;

        return PayoutBatch::create([
            'instructor_id' => $instructor->id,
            'provider' => 'stripe',
            'provider_account_id' => 'acct_test',
            'period_start' => now()->subDays(40 + $this->periodCounter)->toDateString(),
            'period_end' => now()->subDays(30 + $this->periodCounter)->toDateString(),
            'amount_cents' => 5000,
            'status' => $status,
            'gateway_idempotency_key' => (string) Str::uuid(),
            'claimed_at' => now(),
            'completed_at' => $completedAt,
        ]);
    }

    protected function refund(string $status, $completedAt = null): Refund
    {
        $subscription = Subscription::factory()->create(['status' => 'canceled']);

        return Refund::create([
            'subscription_id' => $subscription->id,
            'student_id' => $subscription->student_id,
            'provider' => 'stripe',
            'provider_payment_id' => 'pi_' . Str::random(8),
            'amount_cents' => 700,
            'reason' => 'student_cancel',
            'status' => $status,
            'idempotency_key' => "refund:{$subscription->id}",
            'completed_at' => $completedAt,
        ]);
    }

    // ---------------------------------------------------------------- trend and range

    public function test_trend_compares_periods_and_flips_the_colors_when_down_is_good(): void
    {
        $up = Trend::compare(150, 100);
        $this->assertSame('+50% vs previous 7 days', $up['text']);
        $this->assertSame('success', $up['color']);
        $this->assertSame('heroicon-m-arrow-trending-up', $up['icon']);

        $down = Trend::compare(50, 100);
        $this->assertSame('-50% vs previous 7 days', $down['text']);
        $this->assertSame('danger', $down['color']);
        $this->assertSame('heroicon-m-arrow-trending-down', $down['icon']);

        // Cancellations and refunds: more is bad news, fewer is good news.
        $this->assertSame('danger', Trend::compare(4, 2, 'down')['color']);
        $this->assertSame('success', Trend::compare(2, 4, 'down')['color']);

        // Neutral figures never turn red or green.
        $this->assertSame('gray', Trend::compare(9, 3, 'none')['color']);
        $this->assertSame('gray', Trend::compare(3, 9, 'none')['color']);
    }

    public function test_trend_handles_empty_unchanged_and_new_activity(): void
    {
        $this->assertSame('No activity in either period', Trend::compare(0, 0)['text']);
        $this->assertSame('gray', Trend::compare(0, 0)['color']);

        $new = Trend::compare(5, 0);
        $this->assertSame('New: nothing in the previous 7 days', $new['text']);
        $this->assertSame('success', $new['color']);
        $this->assertSame('danger', Trend::compare(5, 0, 'down')['color']);

        $this->assertSame('Same as the previous 7 days', Trend::compare(100, 100)['text']);
        $this->assertSame('Same as the previous 7 days', Trend::compare(1004, 1000)['text']); // 0.4% rounds to no change
        $this->assertSame('gray', Trend::compare(100, 100)['color']);
    }

    public function test_the_previous_range_is_the_same_length_and_directly_before(): void
    {
        $range = AnalyticsRange::lastDays(7);
        $previous = $range->previous();

        $this->assertCount(7, $previous->dates());
        $this->assertSame($range->from->copy()->subDay()->toDateString(), $previous->to->toDateString());
        $this->assertTrue($previous->to->lt($range->from));
        $this->assertSame($range->from->copy()->subDays(7)->toDateString(), $previous->from->toDateString());
    }

    // ---------------------------------------------------------------- instructor list

    public function test_unanswered_comments_follow_the_reply_rules(): void
    {
        $instructor = $this->userWithRole('instructor');
        $lesson = $this->lessonOf($this->publishedCourse($instructor));
        $elsewhere = $this->lessonOf($this->publishedCourse());
        $s1 = $this->subscribedStudent('S1');
        $s2 = $this->subscribedStudent('S2');
        $s3 = $this->subscribedStudent('S3');

        $this->comment($lesson, $s1->id);                                        // counts: nobody replied

        $answered = $this->comment($lesson, $s2->id);
        $this->comment($lesson, $instructor->id, $answered->id);                 // the instructor answered it

        $replyByStudent = $this->comment($lesson, $s3->id);
        $this->comment($lesson, $s1->id, $replyByStudent->id);                   // another student's reply does not answer it: counts

        $this->comment($lesson, $s1->id)->delete();                              // deleted
        $this->comment($lesson, $instructor->id);                                // the instructor's own comment
        $this->comment($elsewhere, $s1->id);                                     // another instructor's lesson

        $this->assertSame(2, (new InstructorAnalytics($instructor->id))->attention()['unanswered_comments']);
        $this->assertContains('2 comments waiting for your reply', $this->titles(AttentionItems::forInstructor($instructor->id)));
    }

    public function test_the_payout_account_item_depends_on_its_state(): void
    {
        $instructor = $this->userWithRole('instructor');
        $this->publishedCourse($instructor);

        $this->assertContains('Set up your payout account', $this->titles(AttentionItems::forInstructor($instructor->id)));

        $this->payoutAccount($instructor->id, false);
        $titles = $this->titles(AttentionItems::forInstructor($instructor->id));
        $this->assertContains('Finish your payout account', $titles);
        $this->assertNotContains('Set up your payout account', $titles);

        InstructorPayoutAccount::where('instructor_id', $instructor->id)->update(['payouts_enabled' => true]);
        $titles = $this->titles(AttentionItems::forInstructor($instructor->id));
        $this->assertNotContains('Finish your payout account', $titles);
        $this->assertNotContains('Set up your payout account', $titles);
    }

    public function test_the_next_live_session_or_a_nudge_to_schedule_one(): void
    {
        $instructor = $this->userWithRole('instructor');
        $course = $this->publishedCourse($instructor);
        $this->payoutAccount($instructor->id, true);

        $this->assertContains('No live sessions scheduled', $this->titles(AttentionItems::forInstructor($instructor->id)));

        $this->sessionFor($course, ['title' => 'Office hours', 'starts_at' => now()->addDays(2)]);
        $this->sessionFor($course, ['title' => 'Canceled one', 'starts_at' => now()->addDay(), 'status' => 'canceled', 'canceled_at' => now()]);

        $titles = $this->titles(AttentionItems::forInstructor($instructor->id));
        $this->assertContains('Next live session: Office hours', $titles);
        $this->assertNotContains('No live sessions scheduled', $titles);
    }

    public function test_an_instructor_without_courses_gets_a_hint_and_all_clear_only_when_nothing_is_wrong(): void
    {
        $instructor = $this->userWithRole('instructor');

        // No course yet and no payout account: a hint, and no "all clear" because the account needs attention.
        $titles = $this->titles(AttentionItems::forInstructor($instructor->id));
        $this->assertContains('No courses yet', $titles);
        $this->assertContains('Set up your payout account', $titles);
        $this->assertNotContains('Nothing needs your attention', $titles);

        $this->payoutAccount($instructor->id, true);

        $titles = $this->titles(AttentionItems::forInstructor($instructor->id));
        $this->assertContains('Nothing needs your attention', $titles);
        $this->assertContains('No courses yet', $titles);
    }

    // ---------------------------------------------------------------- admin list

    public function test_admin_items_show_each_unhealthy_state_with_its_count(): void
    {
        $this->payoutBatch('uncertain');
        $this->payoutBatch('failed', now()->subDays(2));
        $this->payoutBatch('failed', now()->subDays(10)); // older than a week: not counted
        $this->refund('uncertain');
        $this->refund('failed', now()->subDay());

        $titles = $this->titles(AttentionItems::forAdmin());

        $this->assertContains('1 payout unconfirmed', $titles);
        $this->assertContains('1 payout failed this week', $titles);
        $this->assertContains('1 refund unconfirmed', $titles);
        $this->assertContains('1 refund failed', $titles);
        $this->assertNotContains('All clear', $titles);
    }

    public function test_unpayable_instructors_are_counted_by_the_payout_rules(): void
    {
        $owed = fn (int $instructorId, int $owedCents, int $paidCents = 0) => DB::table('instructor_balances')->insert([
            'instructor_id' => $instructorId, 'owed_cents' => $owedCents, 'paid_cents' => $paidCents, 'updated_at' => now(),
        ]);

        $noAccount = $this->userWithRole('instructor');
        $incomplete = $this->userWithRole('instructor');
        $ready = $this->userWithRole('instructor');
        $belowMinimum = $this->userWithRole('instructor');
        $mostlyPaid = $this->userWithRole('instructor');

        $owed($noAccount->id, 5000);                       // owed, no account: counts
        $owed($incomplete->id, 5000);                      // owed, account incomplete: counts
        $this->payoutAccount($incomplete->id, false);
        $owed($ready->id, 5000);                           // owed, can be paid: fine
        $this->payoutAccount($ready->id, true);
        $owed($belowMinimum->id, 500);                     // below the payout minimum: not yet a problem
        $owed($mostlyPaid->id, 5000, -4500);               // outstanding is only 500: not a problem

        $titles = $this->titles(AttentionItems::forAdmin());

        $this->assertContains('2 instructors owed money but cannot be paid yet', $titles);
    }

    public function test_admin_gets_all_clear_when_nothing_needs_attention(): void
    {
        $this->payoutBatch('succeeded', now()->subDay());
        $this->refund('succeeded', now()->subDay());

        $items = AttentionItems::forAdmin();

        $this->assertSame(['All clear'], $this->titles($items));
        $this->assertSame('success', $items[0]['color']);
    }
}
