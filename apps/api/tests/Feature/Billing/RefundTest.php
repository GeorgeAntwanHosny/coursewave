<?php

namespace Tests\Feature\Billing;

use App\Actions\Billing\CancelSubscriptionAction;
use App\Actions\Billing\GenerateRecognitionSlicesAction;
use App\Actions\Billing\ReconcileRefundAction;
use App\Actions\Billing\SubmitRefundAction;
use App\Jobs\SubmitRefundJob;
use App\Models\LedgerEntry;
use App\Models\Plan;
use App\Models\Refund;
use App\Models\RefundAttempt;
use App\Models\Subscription;
use App\Models\User;
use App\Services\Payments\PaymentGatewayInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;
use Tests\Fakes\FakePaymentGateway;
use Tests\TestCase;

class RefundTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Role::firstOrCreate(['name' => 'student', 'guard_name' => 'web']);
        $this->app->bind(PaymentGatewayInterface::class, FakePaymentGateway::class);
        FakePaymentGateway::reset();
        config(['services.refunds.max_reconcile_attempts' => 5]);
        Queue::fake(); // jobs are inspected, never auto-run — tests drive each transition explicitly
    }

    /** 10-day, $10.00 term started $startedDaysAgo days ago → 100 cents per slice. */
    protected function activeSubscription(int $startedDaysAgo = 3, int $termDays = 10, int $totalCents = 1000): array
    {
        $student = User::factory()->create();
        $student->assignRole('student');
        $plan = Plan::factory()->create(['term_days' => $termDays, 'price_cents' => $totalCents]);

        $subscription = Subscription::factory()->create([
            'student_id' => $student->id,
            'plan_id' => $plan->id,
            'status' => 'active',
            'term_days' => $termDays,
            'total_amount_cents' => $totalCents,
            'provider_payment_id' => 'pi_test_' . Str::random(8),
            'starts_at' => now()->subDays($startedDaysAgo)->toDateString(),
            'ends_at' => now()->subDays($startedDaysAgo)->addDays($termDays)->toDateString(),
        ]);

        app(GenerateRecognitionSlicesAction::class)->execute($subscription->fresh());

        return [$student, $subscription->fresh()];
    }

    protected function cancel(Subscription $subscription, string $reason = 'student_cancel'): ?Refund
    {
        return app(CancelSubscriptionAction::class)->execute($subscription, $reason);
    }

    // ---------------------------------------------------------------- cancellation

    public function test_cancel_refunds_only_unused_days_and_leaves_recognized_and_elapsed_slices_alone(): void
    {
        [, $subscription] = $this->activeSubscription(startedDaysAgo: 3);
        // 3 elapsed slices (today-3 .. today-1), 7 not yet elapsed (today .. today+6).
        $subscription->recognitionSlices()->orderBy('slice_date')->first()
            ->update(['status' => 'recognized', 'recognized_at' => now()]);

        $refund = $this->cancel($subscription);

        $this->assertNotNull($refund);
        $this->assertEquals(700, $refund->amount_cents); // 7 voided slices × 100
        $this->assertEquals('pending', $refund->status);
        $this->assertEquals("refund:{$subscription->id}", $refund->idempotency_key);
        $this->assertEquals('student_cancel', $refund->reason);

        $subscription->refresh();
        $this->assertEquals('canceled', $subscription->status);
        $this->assertEquals(now()->toDateString(), $subscription->ends_at->toDateString()); // truncated to today

        $elapsed = $subscription->recognitionSlices()->where('slice_date', '<', now()->toDateString())->get();
        $this->assertCount(3, $elapsed);
        $this->assertEquals(1, $elapsed->where('status', 'recognized')->count());
        $this->assertEquals(2, $elapsed->where('status', 'pending')->count()); // left for the recognition job

        $future = $subscription->recognitionSlices()->where('slice_date', '>=', now()->toDateString())->get();
        $this->assertCount(7, $future);
        $this->assertTrue($future->every(fn ($s) => $s->status === 'voided'));

        Queue::assertPushed(SubmitRefundJob::class);
        $this->assertEquals(0, LedgerEntry::count()); // no clawback, nothing ledgered
    }

    public function test_nothing_unused_means_no_refund_row(): void
    {
        [, $subscription] = $this->activeSubscription(startedDaysAgo: 10); // every slice already elapsed

        $refund = $this->cancel($subscription);

        $this->assertNull($refund);
        $this->assertEquals(0, Refund::count());
        $this->assertEquals('canceled', $subscription->fresh()->status);
        Queue::assertNothingPushed();
    }

    public function test_cancelling_twice_creates_one_refund(): void
    {
        [, $subscription] = $this->activeSubscription();

        $first = $this->cancel($subscription);
        $second = $this->cancel($subscription->fresh());

        $this->assertEquals($first->id, $second->id);
        $this->assertEquals(1, Refund::count());
    }

    public function test_student_can_cancel_through_the_api(): void
    {
        [$student, $subscription] = $this->activeSubscription();
        $token = $student->createToken('test')->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/subscriptions/current/cancel')
            ->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.subscription.status', 'canceled')
            ->assertJsonPath('data.refund.amount_cents', 700)
            ->assertJsonPath('data.refund.amount_human', '$7.00');

        $this->assertDatabaseHas('refunds', ['subscription_id' => $subscription->id, 'student_id' => $student->id]);
    }

    public function test_current_endpoint_shows_what_a_cancel_would_refund(): void
    {
        [$student] = $this->activeSubscription();
        $token = $student->createToken('test')->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/subscriptions/current')
            ->assertStatus(200)
            ->assertJsonPath('data.refundable_cents', 700)
            ->assertJsonPath('data.refundable_human', '$7.00');
    }

    public function test_cancel_without_an_active_subscription_is_rejected(): void
    {
        $student = User::factory()->create();
        $student->assignRole('student');
        $token = $student->createToken('test')->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/subscriptions/current/cancel')
            ->assertStatus(422)
            ->assertJsonPath('success', false);
    }

    public function test_guest_cannot_cancel(): void
    {
        $this->postJson('/api/subscriptions/current/cancel')->assertStatus(401);
    }

    public function test_switching_plans_refunds_the_old_plans_unused_days(): void
    {
        $student = User::factory()->create();
        $student->assignRole('student');
        $planA = Plan::factory()->create(['price_cents' => 3000, 'term_days' => 30, 'slug' => 'a']);
        $planB = Plan::factory()->create(['price_cents' => 4999, 'term_days' => 90, 'slug' => 'b']);
        $token = $student->createToken('test')->plainTextToken;

        foreach ([$planA, $planB] as $plan) {
            $this->withHeader('Authorization', "Bearer {$token}")->postJson('/api/subscriptions/checkout', ['plan_id' => $plan->id]);
            $sessionId = Subscription::where('student_id', $student->id)->where('plan_id', $plan->id)->latest('id')->first()->provider_checkout_id;
            $this->withHeader('Authorization', "Bearer {$token}")->postJson('/api/subscriptions/confirm', ['session_id' => $sessionId]);
        }

        $oldSubscription = Subscription::where('student_id', $student->id)->where('plan_id', $planA->id)->first();

        $this->assertEquals('canceled', $oldSubscription->status);
        $refund = Refund::where('subscription_id', $oldSubscription->id)->firstOrFail();
        $this->assertEquals(3000, $refund->amount_cents); // all 30 slices were still in the future
        $this->assertEquals('plan_switch', $refund->reason);
    }

    // ---------------------------------------------------------------- provider flow

    public function test_successful_refund_is_recorded_logged_and_writes_no_ledger_entry(): void
    {
        [, $subscription] = $this->activeSubscription();
        $refund = $this->cancel($subscription);

        app(SubmitRefundAction::class)->execute($refund);

        $refund->refresh();
        $this->assertEquals('succeeded', $refund->status);
        $this->assertNotNull($refund->provider_refund_id);
        $this->assertArrayHasKey("refund:{$subscription->id}", FakePaymentGateway::$refunds);
        $this->assertEquals(0, LedgerEntry::count());

        $attempt = RefundAttempt::where('refund_id', $refund->id)->firstOrFail();
        $this->assertEquals('refund_initial', $attempt->operation);
        $this->assertEquals('succeeded', $attempt->outcome);
        $this->assertEquals('submitted', $attempt->status_before);
        $this->assertEquals('succeeded', $attempt->status_after);
        $this->assertEquals('refund', $attempt->response['object']);
        $this->assertNotNull($attempt->provider_request_id);
    }

    public function test_explicit_decline_marks_failed_and_is_logged(): void
    {
        FakePaymentGateway::$refundMode = 'decline';
        [, $subscription] = $this->activeSubscription();
        $refund = $this->cancel($subscription);

        app(SubmitRefundAction::class)->execute($refund);

        $this->assertEquals('failed', $refund->fresh()->status);
        $attempt = RefundAttempt::where('refund_id', $refund->id)->firstOrFail();
        $this->assertEquals('declined', $attempt->outcome);
        $this->assertEquals('charge_already_refunded', $attempt->error_code);
        $this->assertEquals(400, $attempt->http_status);
    }

    public function test_missing_payment_reference_fails_without_calling_the_provider(): void
    {
        [, $subscription] = $this->activeSubscription();
        $subscription->update(['provider_payment_id' => null]);
        $refund = $this->cancel($subscription->fresh());

        app(SubmitRefundAction::class)->execute($refund);

        $refund->refresh();
        $this->assertEquals('failed', $refund->status);
        $this->assertStringContainsString('no payment reference', $refund->failure_reason);
        $this->assertCount(0, FakePaymentGateway::$refunds);
    }

    public function test_timeout_is_uncertain_and_reconcile_finds_the_refund_that_landed(): void
    {
        FakePaymentGateway::$refundMode = 'timeout_created';
        [, $subscription] = $this->activeSubscription();
        $refund = $this->cancel($subscription);

        app(SubmitRefundAction::class)->execute($refund);
        $this->assertEquals('uncertain', $refund->fresh()->status);

        app(ReconcileRefundAction::class)->execute($refund->id);

        $this->assertEquals('succeeded', $refund->fresh()->status);
        $this->assertCount(1, FakePaymentGateway::$refunds); // still exactly one refund

        $attempts = RefundAttempt::where('refund_id', $refund->id)->orderBy('attempt_number')->get();
        $this->assertEquals(['refund_initial', 'lookup'], $attempts->pluck('operation')->all());
        $this->assertEquals(['uncertain', 'found'], $attempts->pluck('outcome')->all());
    }

    public function test_reconcile_resends_with_the_same_key_when_no_refund_exists(): void
    {
        FakePaymentGateway::$refundMode = 'timeout_not_created';
        [, $subscription] = $this->activeSubscription();
        $refund = $this->cancel($subscription);
        $keyBefore = $refund->idempotency_key;

        app(SubmitRefundAction::class)->execute($refund);
        $this->assertCount(0, FakePaymentGateway::$refunds);

        FakePaymentGateway::$refundMode = 'success'; // provider recovered
        app(ReconcileRefundAction::class)->execute($refund->id);

        $refund->refresh();
        $this->assertEquals('succeeded', $refund->status);
        $this->assertEquals($keyBefore, $refund->idempotency_key); // never regenerated
        $this->assertCount(1, FakePaymentGateway::$refunds);

        $attempts = RefundAttempt::where('refund_id', $refund->id)->orderBy('attempt_number')->get();
        $this->assertEquals(['refund_initial', 'lookup', 'refund_resend'], $attempts->pluck('operation')->all());
        $this->assertEquals(['uncertain', 'not_found', 'succeeded'], $attempts->pluck('outcome')->all());
        $this->assertCount(1, $attempts->pluck('idempotency_key')->unique());
    }

    public function test_reconcile_does_not_resend_once_the_idempotency_window_has_expired(): void
    {
        FakePaymentGateway::$refundMode = 'timeout_not_created';
        [, $subscription] = $this->activeSubscription();
        $refund = $this->cancel($subscription);

        app(SubmitRefundAction::class)->execute($refund);
        Refund::whereKey($refund->id)->update(['submitted_at' => now()->subHours(30)]);

        FakePaymentGateway::$refundMode = 'success'; // a resend WOULD succeed, so a wrongful resend is detectable
        app(ReconcileRefundAction::class)->execute($refund->id);

        $refund->refresh();
        $this->assertEquals('uncertain', $refund->status); // left for an admin
        $this->assertCount(0, FakePaymentGateway::$refunds); // nothing was resent
        $this->assertStringContainsString('idempotency window', $refund->failure_reason);
    }

    public function test_attempt_rows_are_append_only(): void
    {
        [, $subscription] = $this->activeSubscription();
        $refund = $this->cancel($subscription);
        app(SubmitRefundAction::class)->execute($refund);
        $attempt = RefundAttempt::firstOrFail();

        $this->expectException(\LogicException::class);
        $attempt->update(['outcome' => 'declined']);
    }
}
