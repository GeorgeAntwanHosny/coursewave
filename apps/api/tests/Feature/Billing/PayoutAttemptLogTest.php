<?php

namespace Tests\Feature\Billing;

use App\Actions\Billing\ClaimPayoutBatchAction;
use App\Actions\Billing\ReconcilePayoutBatchAction;
use App\Actions\Billing\SubmitPayoutBatchAction;
use App\Models\InstructorPayoutAccount;
use App\Models\PayoutBatch;
use App\Models\PayoutBatchAttempt;
use App\Models\User;
use App\Services\Payments\PaymentGatewayInterface;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;
use Tests\Fakes\FakePaymentGateway;
use Tests\TestCase;

class PayoutAttemptLogTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Role::firstOrCreate(['name' => 'instructor', 'guard_name' => 'web']);
        $this->app->bind(PaymentGatewayInterface::class, FakePaymentGateway::class);
        FakePaymentGateway::reset();
        config(['services.payouts.minimum_cents' => 1000, 'services.payouts.max_reconcile_attempts' => 5]);
        Queue::fake();
    }

    protected function claimedBatch(): PayoutBatch
    {
        $instructor = User::factory()->create();
        $instructor->assignRole('instructor');

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

        return app(ClaimPayoutBatchAction::class)->execute($instructor, Carbon::yesterday());
    }

    public function test_first_time_success_is_logged_with_the_gateway_response(): void
    {
        $batch = $this->claimedBatch();
        app(SubmitPayoutBatchAction::class)->execute($batch);

        $attempts = PayoutBatchAttempt::where('payout_batch_id', $batch->id)->get();
        $this->assertCount(1, $attempts);

        $a = $attempts->first();
        $this->assertEquals(1, $a->attempt_number);
        $this->assertEquals('transfer_initial', $a->operation);
        $this->assertEquals('succeeded', $a->outcome);
        $this->assertEquals('submitted', $a->status_before);
        $this->assertEquals('succeeded', $a->status_after);
        $this->assertEquals(5000, $a->amount_cents);
        $this->assertEquals($batch->gateway_idempotency_key, $a->idempotency_key);
        $this->assertEquals($batch->fresh()->provider_transfer_id, $a->provider_transfer_id);
        $this->assertEquals('transfer', $a->response['object']);
        $this->assertNotNull($a->provider_request_id);
        $this->assertNotNull($a->duration_ms);
    }

    public function test_decline_is_logged_with_error_details(): void
    {
        FakePaymentGateway::$transferMode = 'decline';
        $batch = $this->claimedBatch();
        app(SubmitPayoutBatchAction::class)->execute($batch);

        $a = PayoutBatchAttempt::where('payout_batch_id', $batch->id)->firstOrFail();
        $this->assertEquals('declined', $a->outcome);
        $this->assertEquals('failed', $a->status_after);
        $this->assertEquals(400, $a->http_status);
        $this->assertEquals('balance_insufficient', $a->error_code);
        $this->assertEquals('req_fake_decline', $a->provider_request_id);
        $this->assertStringContainsString('Insufficient funds', $a->error_message);
        $this->assertEquals('balance_insufficient', $a->response['error']['code']);
    }

    public function test_timeout_then_reconcile_finding_the_transfer_logs_both_steps(): void
    {
        FakePaymentGateway::$transferMode = 'timeout_created';
        $batch = $this->claimedBatch();
        app(SubmitPayoutBatchAction::class)->execute($batch);
        app(ReconcilePayoutBatchAction::class)->execute($batch->id);

        $attempts = PayoutBatchAttempt::where('payout_batch_id', $batch->id)->orderBy('attempt_number')->get();
        $this->assertCount(2, $attempts);

        $this->assertEquals([1, 2], $attempts->pluck('attempt_number')->all());
        $this->assertEquals(['transfer_initial', 'lookup'], $attempts->pluck('operation')->all());
        $this->assertEquals(['uncertain', 'found'], $attempts->pluck('outcome')->all());
        $this->assertEquals('uncertain', $attempts[0]->status_after);
        $this->assertEquals('reconciling', $attempts[1]->status_before);
        $this->assertEquals('succeeded', $attempts[1]->status_after);
        $this->assertNotNull($attempts[1]->response); // what the lookup actually found
    }

    public function test_timeout_then_reconcile_resend_logs_the_whole_retry_trail_with_one_key(): void
    {
        FakePaymentGateway::$transferMode = 'timeout_not_created';
        $batch = $this->claimedBatch();
        app(SubmitPayoutBatchAction::class)->execute($batch);

        FakePaymentGateway::$transferMode = 'success';
        app(ReconcilePayoutBatchAction::class)->execute($batch->id);

        $attempts = PayoutBatchAttempt::where('payout_batch_id', $batch->id)->orderBy('attempt_number')->get();

        $this->assertEquals([1, 2, 3], $attempts->pluck('attempt_number')->all());
        $this->assertEquals(['transfer_initial', 'lookup', 'transfer_resend'], $attempts->pluck('operation')->all());
        $this->assertEquals(['uncertain', 'not_found', 'succeeded'], $attempts->pluck('outcome')->all());
        $this->assertCount(1, $attempts->pluck('idempotency_key')->unique()); // never regenerated
        $this->assertEquals('succeeded', $batch->fresh()->status);
    }

    public function test_attempt_rows_are_append_only(): void
    {
        $batch = $this->claimedBatch();
        app(SubmitPayoutBatchAction::class)->execute($batch);
        $attempt = PayoutBatchAttempt::firstOrFail();

        $this->expectException(\LogicException::class);
        $attempt->update(['outcome' => 'declined']);
    }

    public function test_attempt_rows_cannot_be_deleted(): void
    {
        $batch = $this->claimedBatch();
        app(SubmitPayoutBatchAction::class)->execute($batch);
        $attempt = PayoutBatchAttempt::firstOrFail();

        $this->expectException(\LogicException::class);
        $attempt->delete();
    }
}
