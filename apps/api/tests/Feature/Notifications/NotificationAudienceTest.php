<?php

namespace Tests\Feature\Notifications;

use App\Actions\Billing\CancelSubscriptionAction;
use App\Actions\Billing\FinalizePayoutBatchAction;
use App\Actions\Billing\FinalizeRefundAction;
use App\Actions\Billing\GenerateRecognitionSlicesAction;
use App\Actions\Billing\RefreshPayoutAccountStatusAction;
use App\Jobs\ReconcilePayoutBatchJob;
use App\Models\Comment;
use App\Models\Course;
use App\Models\InstructorPayoutAccount;
use App\Models\Lesson;
use App\Models\LessonProgress;
use App\Models\Module;
use App\Models\PayoutBatch;
use App\Models\Plan;
use App\Models\Refund;
use App\Models\Subscription;
use App\Models\User;
use App\Notifications\AdminBillingNotification;
use App\Notifications\CertificateIssuedNotification;
use App\Notifications\CommentReplyNotification;
use App\Notifications\InstructorNotification;
use App\Services\Payments\PaymentGatewayInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;
use Tests\Fakes\FakePaymentGateway;
use Tests\TestCase;

class NotificationAudienceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        foreach (['student', 'instructor', 'admin'] as $role) {
            Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);
        }
        $this->app->bind(PaymentGatewayInterface::class, FakePaymentGateway::class);
        FakePaymentGateway::reset();
        config(['services.payouts.minimum_cents' => 1000, 'services.payouts.max_reconcile_attempts' => 5]);
        Notification::fake();
        Queue::fake();
    }

    protected function actingWith(string $token): static
    {
        $this->app['auth']->forgetGuards(); // convention #37

        return $this->withHeader('Authorization', "Bearer {$token}");
    }

    protected function userWithRole(string $role, ?string $name = null): User
    {
        $user = User::factory()->create($name ? ['name' => $name] : []);
        $user->assignRole($role);

        return $user;
    }

    /** @return array{0: User, 1: string} */
    protected function subscribedStudent(string $name = 'Sam Student'): array
    {
        $student = $this->userWithRole('student', $name);
        Subscription::factory()->create([
            'student_id' => $student->id,
            'plan_id' => Plan::factory()->create()->id,
            'status' => 'active',
        ]);

        return [$student, $student->createToken('test')->plainTextToken];
    }

    /** @return array{0: User, 1: Lesson} */
    protected function lessonWithInstructor(): array
    {
        $instructor = $this->userWithRole('instructor', 'Ada Instructor');
        $course = Course::factory()->create(['instructor_id' => $instructor->id, 'status' => 'published', 'title' => 'Laravel Mastery']);
        $module = Module::factory()->create(['course_id' => $course->id]);
        $lesson = Lesson::factory()->create(['module_id' => $module->id, 'type' => 'text', 'body' => 'short']);

        return [$instructor, $lesson];
    }

    protected function payoutBatch(User $instructor, string $status = 'submitted'): PayoutBatch
    {
        return PayoutBatch::create([
            'instructor_id' => $instructor->id,
            'provider' => 'stripe',
            'provider_account_id' => 'acct_test',
            'period_start' => now()->subDays(7)->toDateString(),
            'period_end' => now()->subDay()->toDateString(),
            'amount_cents' => 5000,
            'status' => $status,
            'gateway_idempotency_key' => (string) Str::uuid(),
            'claimed_at' => now(),
        ]);
    }

    protected function refundFor(Subscription $subscription): Refund
    {
        return Refund::create([
            'subscription_id' => $subscription->id,
            'student_id' => $subscription->student_id,
            'provider' => 'stripe',
            'provider_payment_id' => 'pi_test',
            'amount_cents' => 700,
            'reason' => 'student_cancel',
            'status' => 'submitted',
            'idempotency_key' => "refund:{$subscription->id}",
        ]);
    }

    // ---------------------------------------------------------------- instructor: comments

    public function test_a_new_top_level_comment_notifies_the_lessons_instructor(): void
    {
        [$instructor, $lesson] = $this->lessonWithInstructor();
        [, $token] = $this->subscribedStudent('Bob Student');

        $this->actingWith($token)->postJson("/api/learning/lessons/{$lesson->id}/comments", ['body' => 'Great lesson!'])
            ->assertStatus(201);

        Notification::assertSentTo($instructor, InstructorNotification::class, function ($n) use ($lesson) {
            return $n->kind === 'lesson_comment'
                && str_contains($n->title, 'New comment')
                && str_contains($n->title, $lesson->title)
                && str_contains($n->body, 'Bob Student')
                && str_contains($n->url, '/instructor/');
        });
    }

    public function test_a_student_reply_in_someone_elses_thread_notifies_both_the_parent_author_and_the_instructor(): void
    {
        [$instructor, $lesson] = $this->lessonWithInstructor();
        [$parentAuthor] = $this->subscribedStudent('Ann Author');
        [, $replierToken] = $this->subscribedStudent('Bob Replier');
        $parent = Comment::create([
            'commentable_type' => Lesson::class, 'commentable_id' => $lesson->id,
            'user_id' => $parentAuthor->id, 'body' => 'Question?',
        ]);

        $this->actingWith($replierToken)->postJson("/api/learning/lessons/{$lesson->id}/comments", [
            'body' => 'Answer', 'parent_id' => $parent->id,
        ])->assertStatus(201);

        Notification::assertSentTo($parentAuthor, CommentReplyNotification::class);
        Notification::assertSentTo($instructor, InstructorNotification::class, fn ($n) => str_contains($n->title, 'New reply'));
    }

    public function test_a_student_reply_to_the_instructors_own_comment_sends_only_the_reply_notification(): void
    {
        [$instructor, $lesson] = $this->lessonWithInstructor();
        [, $token] = $this->subscribedStudent();
        $parent = Comment::create([
            'commentable_type' => Lesson::class, 'commentable_id' => $lesson->id,
            'user_id' => $instructor->id, 'body' => 'Any questions?',
        ]);

        $this->actingWith($token)->postJson("/api/learning/lessons/{$lesson->id}/comments", [
            'body' => 'Yes, one', 'parent_id' => $parent->id,
        ])->assertStatus(201);

        Notification::assertSentTo($instructor, CommentReplyNotification::class);
        Notification::assertNotSentTo($instructor, InstructorNotification::class); // no duplicate
    }

    public function test_the_instructor_commenting_on_their_own_lesson_notifies_nobody(): void
    {
        [$instructor, $lesson] = $this->lessonWithInstructor();
        $token = $instructor->createToken('test')->plainTextToken;

        $this->actingWith($token)->postJson("/api/learning/lessons/{$lesson->id}/comments", ['body' => 'Welcome!'])
            ->assertStatus(201);

        Notification::assertNothingSent();
    }

    public function test_admins_are_never_notified_about_comments(): void
    {
        [, $lesson] = $this->lessonWithInstructor();
        $admin = $this->userWithRole('admin');
        [$parentAuthor] = $this->subscribedStudent('Ann Author');
        [, $token] = $this->subscribedStudent();
        $parent = Comment::create([
            'commentable_type' => Lesson::class, 'commentable_id' => $lesson->id,
            'user_id' => $parentAuthor->id, 'body' => 'Question?',
        ]);

        $this->actingWith($token)->postJson("/api/learning/lessons/{$lesson->id}/comments", ['body' => 'Top level'])->assertStatus(201);
        $this->actingWith($token)->postJson("/api/learning/lessons/{$lesson->id}/comments", ['body' => 'Reply', 'parent_id' => $parent->id])->assertStatus(201);

        Notification::assertNotSentTo($admin, InstructorNotification::class);
        Notification::assertNotSentTo($admin, AdminBillingNotification::class);
        Notification::assertNotSentTo($admin, CommentReplyNotification::class);
    }

    // ---------------------------------------------------------------- instructor: courses + payout account

    public function test_a_course_completion_notifies_the_instructor_once(): void
    {
        [$instructor, $lesson] = $this->lessonWithInstructor();
        [, $token] = $this->subscribedStudent('Sam Student');

        $this->actingWith($token)->postJson("/api/learning/lessons/{$lesson->id}/progress", ['completed' => true]);
        $this->actingWith($token)->postJson("/api/learning/lessons/{$lesson->id}/progress", ['completed' => true]);

        Notification::assertSentTo($instructor, InstructorNotification::class, function ($n) {
            return $n->kind === 'course_completed' && str_contains($n->body, 'Sam Student') && str_contains($n->body, 'Laravel Mastery');
        });
        Notification::assertSentTimes(InstructorNotification::class, 1);
    }

    public function test_the_backfill_command_tells_students_but_does_not_flood_instructors(): void
    {
        [$instructor, $lesson] = $this->lessonWithInstructor();
        [$student] = $this->subscribedStudent();

        LessonProgress::create([
            'student_id' => $student->id,
            'lesson_id' => $lesson->id,
            'course_id' => $lesson->module->course_id,
            'status' => 'completed',
            'watched_seconds' => 0,
            'completed_at' => now(),
        ]);

        $this->artisan('certificates:sync')->assertExitCode(0);

        Notification::assertSentTo($student, CertificateIssuedNotification::class);
        Notification::assertNotSentTo($instructor, InstructorNotification::class);
    }

    public function test_the_course_completed_notification_can_be_switched_off(): void
    {
        config(['coursewave.notifications.instructor_course_completed' => false]);
        [$instructor, $lesson] = $this->lessonWithInstructor();
        [, $token] = $this->subscribedStudent();

        $this->actingWith($token)->postJson("/api/learning/lessons/{$lesson->id}/progress", ['completed' => true]);

        Notification::assertNotSentTo($instructor, InstructorNotification::class);
    }

    public function test_the_instructor_is_told_only_when_the_payout_account_status_changes(): void
    {
        $instructor = $this->userWithRole('instructor');
        $account = InstructorPayoutAccount::create([
            'instructor_id' => $instructor->id,
            'provider' => 'stripe',
            'provider_account_id' => 'acct_x',
            'provider_account_version' => 'v2',
            'payouts_enabled' => false,
        ]);
        $refresh = app(RefreshPayoutAccountStatusAction::class);

        FakePaymentGateway::$accountEnabled = true;
        $account = $refresh->execute($account);
        $refresh->execute($account); // no change, so no second notification

        Notification::assertSentTo($instructor, InstructorNotification::class, fn ($n) => $n->kind === 'payout_account_ready');
        Notification::assertSentTimes(InstructorNotification::class, 1);

        FakePaymentGateway::$accountEnabled = false;
        $refresh->execute($account->fresh());

        Notification::assertSentTo($instructor, InstructorNotification::class, fn ($n) => $n->kind === 'payout_account_attention');
        Notification::assertSentTimes(InstructorNotification::class, 2);
    }

    // ---------------------------------------------------------------- payouts

    public function test_a_successful_payout_notifies_the_instructor_and_the_admins(): void
    {
        $instructor = $this->userWithRole('instructor', 'Ada Instructor');
        $admin = $this->userWithRole('admin');
        $batch = $this->payoutBatch($instructor);

        app(FinalizePayoutBatchAction::class)->markSucceeded($batch->id, 'tr_test');

        Notification::assertSentTo($admin, AdminBillingNotification::class, function ($n) {
            return $n->kind === 'admin_payout_processed' && str_contains($n->body, '$50.00') && str_contains($n->body, 'Ada Instructor');
        });
        Notification::assertSentTo($instructor, \App\Notifications\PayoutProcessedNotification::class);
    }

    public function test_a_failed_payout_gives_the_instructor_a_generic_message_and_the_admins_the_reason(): void
    {
        $instructor = $this->userWithRole('instructor');
        $admin = $this->userWithRole('admin');
        $batch = $this->payoutBatch($instructor);

        app(FinalizePayoutBatchAction::class)->markFailed($batch->id, 'balance_insufficient');

        Notification::assertSentTo($instructor, InstructorNotification::class, function ($n) {
            return $n->kind === 'payout_failed' && ! str_contains($n->body, 'balance_insufficient') && str_contains($n->body, 'next payout run');
        });
        Notification::assertSentTo($admin, AdminBillingNotification::class, function ($n) {
            return $n->kind === 'admin_payout_failed' && str_contains($n->body, 'balance_insufficient');
        });
    }

    public function test_a_payout_that_runs_out_of_reconcile_attempts_alerts_the_admins(): void
    {
        config(['services.payouts.max_reconcile_attempts' => 0]);
        $instructor = $this->userWithRole('instructor');
        $admin = $this->userWithRole('admin');
        $batch = $this->payoutBatch($instructor);

        app(FinalizePayoutBatchAction::class)->markUncertain($batch->id, 'Timed out');

        Notification::assertSentTo($admin, AdminBillingNotification::class, fn ($n) => $n->kind === 'admin_payout_stuck');
        Queue::assertNotPushed(ReconcilePayoutBatchJob::class);
    }

    public function test_a_payout_that_can_still_be_reconciled_does_not_alert_anyone(): void
    {
        $instructor = $this->userWithRole('instructor');
        $this->userWithRole('admin');
        $batch = $this->payoutBatch($instructor);

        app(FinalizePayoutBatchAction::class)->markUncertain($batch->id, 'Timed out');

        Notification::assertNothingSent();
        Queue::assertPushed(ReconcilePayoutBatchJob::class);
    }

    public function test_the_payout_run_sends_the_admins_a_summary(): void
    {
        $instructor = $this->userWithRole('instructor');
        $admin = $this->userWithRole('admin');

        InstructorPayoutAccount::create([
            'instructor_id' => $instructor->id,
            'provider' => 'stripe',
            'provider_account_id' => 'acct_' . Str::random(8),
            'payouts_enabled' => true,
        ]);
        DB::table('ledger_entries')->insert([
            'instructor_id' => $instructor->id,
            'entry_type' => 'accrual',
            'amount_cents' => 5000,
            'idempotency_key' => 'test:' . Str::uuid(),
            'created_at' => now()->subDay()->endOfDay(),
            'recorded_at' => now(),
        ]);
        DB::table('instructor_balances')->insert([
            'instructor_id' => $instructor->id, 'owed_cents' => 5000, 'paid_cents' => 0, 'updated_at' => now(),
        ]);

        $this->artisan('payouts:run')->assertExitCode(0);

        Notification::assertSentTo($admin, AdminBillingNotification::class, function ($n) {
            return $n->kind === 'admin_payout_run' && str_contains($n->body, '1 payout batch') && str_contains($n->body, '$50.00');
        });
    }

    public function test_an_empty_payout_run_sends_nothing(): void
    {
        $this->userWithRole('admin');

        $this->artisan('payouts:run')->assertExitCode(0);

        Notification::assertNothingSent();
    }

    // ---------------------------------------------------------------- refunds

    public function test_a_successful_refund_notifies_the_student_and_the_admins(): void
    {
        $subscription = Subscription::factory()->create(['status' => 'canceled']);
        $admin = $this->userWithRole('admin');
        $refund = $this->refundFor($subscription);

        app(FinalizeRefundAction::class)->markSucceeded($refund->id, 're_test');

        Notification::assertSentTo($admin, AdminBillingNotification::class, fn ($n) => $n->kind === 'admin_refund_processed' && str_contains($n->body, '$7.00'));
        Notification::assertSentTo($subscription->student, \App\Notifications\RefundProcessedNotification::class);
    }

    public function test_a_failed_refund_alerts_the_admins_and_tells_the_student_nothing(): void
    {
        $subscription = Subscription::factory()->create(['status' => 'canceled']);
        $admin = $this->userWithRole('admin');
        $refund = $this->refundFor($subscription);

        app(FinalizeRefundAction::class)->markFailed($refund->id, 'charge_already_refunded');

        Notification::assertSentTo($admin, AdminBillingNotification::class, function ($n) {
            return $n->kind === 'admin_refund_failed' && str_contains($n->body, 'charge_already_refunded');
        });
        Notification::assertNotSentTo($subscription->student, \App\Notifications\RefundProcessedNotification::class);
        Notification::assertNotSentTo($subscription->student, AdminBillingNotification::class);
    }

    public function test_a_refund_that_needs_a_human_alerts_the_admins(): void
    {
        $subscription = Subscription::factory()->create(['status' => 'canceled']);
        $admin = $this->userWithRole('admin');
        $refund = $this->refundFor($subscription);

        // The idempotency-window path: no more automatic reconciliation is scheduled.
        app(FinalizeRefundAction::class)->markUncertain($refund->id, 'Window expired', scheduleReconcile: false);

        Notification::assertSentTo($admin, AdminBillingNotification::class, fn ($n) => $n->kind === 'admin_refund_stuck');
    }

    // ---------------------------------------------------------------- subscriptions

    protected function activeSubscriptionWithSlices(): Subscription
    {
        $student = $this->userWithRole('student', 'Sam Student');
        $plan = Plan::factory()->create(['name' => 'Monthly', 'term_days' => 10, 'price_cents' => 1000]);
        $subscription = Subscription::factory()->create([
            'student_id' => $student->id,
            'plan_id' => $plan->id,
            'status' => 'active',
            'term_days' => 10,
            'total_amount_cents' => 1000,
            'provider_payment_id' => 'pi_test_' . Str::random(6),
            'starts_at' => now()->toDateString(),
            'ends_at' => now()->addDays(10)->toDateString(),
        ]);
        app(GenerateRecognitionSlicesAction::class)->execute($subscription->fresh());

        return $subscription->fresh();
    }

    public function test_a_student_cancellation_alerts_the_admins_with_the_refund_amount(): void
    {
        $admin = $this->userWithRole('admin');
        $subscription = $this->activeSubscriptionWithSlices();

        app(CancelSubscriptionAction::class)->execute($subscription, 'student_cancel');
        app(CancelSubscriptionAction::class)->execute($subscription->fresh(), 'student_cancel'); // repeat is silent

        Notification::assertSentTo($admin, AdminBillingNotification::class, function ($n) {
            return $n->kind === 'admin_subscription_canceled' && str_contains($n->body, 'Sam Student') && str_contains($n->body, '$10.00');
        });
        Notification::assertSentTimes(AdminBillingNotification::class, 1);
    }

    public function test_a_plan_switch_cancellation_is_not_reported_separately(): void
    {
        $this->userWithRole('admin');
        $subscription = $this->activeSubscriptionWithSlices();

        app(CancelSubscriptionAction::class)->execute($subscription, 'plan_switch');

        Notification::assertNothingSent();
    }

    public function test_a_new_paid_subscription_alerts_the_admins_and_mentions_a_switch(): void
    {
        $admin = $this->userWithRole('admin');
        $student = $this->userWithRole('student', 'Sam Student');
        $token = $student->createToken('test')->plainTextToken;
        $planA = Plan::factory()->create(['name' => 'Monthly', 'price_cents' => 1999, 'term_days' => 30, 'slug' => 'a']);
        $planB = Plan::factory()->create(['name' => 'Annual', 'price_cents' => 14999, 'term_days' => 365, 'slug' => 'b']);

        foreach ([$planA, $planB] as $plan) {
            $this->actingWith($token)->postJson('/api/subscriptions/checkout', ['plan_id' => $plan->id])->assertStatus(200);
            $sessionId = Subscription::where('student_id', $student->id)->where('plan_id', $plan->id)->latest('id')->first()->provider_checkout_id;
            $this->actingWith($token)->postJson('/api/subscriptions/confirm', ['session_id' => $sessionId])->assertStatus(200);
        }

        Notification::assertSentTo($admin, AdminBillingNotification::class, function ($n) {
            return $n->kind === 'admin_new_subscription' && str_contains($n->body, 'Monthly') && ! str_contains($n->body, 'Switched');
        });
        Notification::assertSentTo($admin, AdminBillingNotification::class, function ($n) {
            return $n->kind === 'admin_new_subscription' && str_contains($n->body, 'Annual') && str_contains($n->body, 'Switched from Monthly');
        });
        // The switch's cancellation is folded into the second notification, never sent on its own.
        Notification::assertNotSentTo($admin, AdminBillingNotification::class, fn ($n) => $n->kind === 'admin_subscription_canceled');
        Notification::assertSentTimes(AdminBillingNotification::class, 2);
    }

    public function test_confirming_twice_alerts_the_admins_only_once(): void
    {
        $admin = $this->userWithRole('admin');
        $student = $this->userWithRole('student');
        $token = $student->createToken('test')->plainTextToken;
        $plan = Plan::factory()->create(['price_cents' => 1999, 'term_days' => 30]);

        $this->actingWith($token)->postJson('/api/subscriptions/checkout', ['plan_id' => $plan->id]);
        $sessionId = Subscription::where('student_id', $student->id)->first()->provider_checkout_id;

        $this->actingWith($token)->postJson('/api/subscriptions/confirm', ['session_id' => $sessionId])->assertStatus(200);
        $this->actingWith($token)->postJson('/api/subscriptions/confirm', ['session_id' => $sessionId])->assertStatus(200);

        Notification::assertSentTo($admin, AdminBillingNotification::class);
        Notification::assertSentTimes(AdminBillingNotification::class, 1);
    }

    public function test_the_new_subscription_alert_can_be_switched_off(): void
    {
        config(['coursewave.notifications.admin_new_subscription' => false]);
        $this->userWithRole('admin');
        $student = $this->userWithRole('student');
        $token = $student->createToken('test')->plainTextToken;
        $plan = Plan::factory()->create(['price_cents' => 1999, 'term_days' => 30]);

        $this->actingWith($token)->postJson('/api/subscriptions/checkout', ['plan_id' => $plan->id]);
        $sessionId = Subscription::where('student_id', $student->id)->first()->provider_checkout_id;
        $this->actingWith($token)->postJson('/api/subscriptions/confirm', ['session_id' => $sessionId])->assertStatus(200);

        Notification::assertNothingSent();
    }
}
