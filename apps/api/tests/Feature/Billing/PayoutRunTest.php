<?php

namespace Tests\Feature\Billing;

use App\Actions\Billing\ClaimPayoutBatchAction;
use App\Actions\Billing\FinalizePayoutBatchAction;
use App\Actions\Billing\ReconcilePayoutBatchAction;
use App\Actions\Billing\SubmitPayoutBatchAction;
use App\Jobs\ReconcilePayoutBatchJob;
use App\Jobs\SubmitPayoutBatchJob;
use App\Models\InstructorBalance;
use App\Models\InstructorPayoutAccount;
use App\Models\LedgerEntry;
use App\Models\PayoutBatch;
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

class PayoutRunTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Role::firstOrCreate(['name' => 'instructor', 'guard_name' => 'web']);
        $this->app->bind(PaymentGatewayInterface::class, FakePaymentGateway::class);
        FakePaymentGateway::reset();
        config(['services.payouts.minimum_cents' => 1000, 'services.payouts.max_reconcile_attempts' => 5]);
        Queue::fake(); // jobs are inspected, never auto-run — tests drive each state transition explicitly
    }

    protected function makeInstructor(int $owedCents = 5000, bool $withAccount = true): User
    {
        $instructor = User::factory()->create();
        $instructor->assignRole('instructor');

        if ($withAccount) {
            InstructorPayoutAccount::create([
                'instructor_id' => $instructor->id,
                'provider' => 'stripe',
                'provider_account_id' => 'acct_' . Str::random(8),
                'payouts_enabled' => true,
            ]);
        }

        DB::table('ledger_entries')->insert([
            'instructor_id' => $instructor->id,
            'entry_type' => 'accrual',
            'amount_cents' => $owedCents,
            'subscription_id' => null,
            'recognition_slice_id' => null,
            'idempotency_key' => 'test:' . Str::uuid(),
            'created_at' => now()->subDay()->endOfDay(),
            'recorded_at' => now(),
        ]);
        DB::table('instructor_balances')->insert([
            'instructor_id' => $instructor->id,
            'owed_cents' => $owedCents,
            'paid_cents' => 0,
            'updated_at' => now(),
        ]);

        return $instructor;
    }

    protected function claim(User $instructor, ?Carbon $periodEnd = null): ?PayoutBatch
    {
        return app(ClaimPayoutBatchAction::class)->execute($instructor, $periodEnd ?? Carbon::yesterday());
    }

    public function test_successful_payout_writes_negative_ledger_entry_and_updates_balance(): void
    {
        $instructor = $this->makeInstructor(5000);

        $batch = $this->claim($instructor);
        $this->assertEquals('pending', $batch->status);
        $this->assertEquals(5000, $batch->amount_cents);

        app(SubmitPayoutBatchAction::class)->execute($batch);

        $batch->refresh();
        $this->assertEquals('succeeded', $batch->status);
        $this->assertNotNull($batch->provider_transfer_id);

        $entry = LedgerEntry::where('entry_type', 'payout')->first();
        $this->assertEquals(-5000, $entry->amount_cents);
        $this->assertEquals($batch->id, $entry->payout_batch_id);

        $balance = InstructorBalance::find($instructor->id);
        $this->assertEquals(-5000, $balance->paid_cents);
        $this->assertEquals(0, $balance->owed_cents + $balance->paid_cents); // outstanding cleared
        $this->assertCount(1, FakePaymentGateway::$transfers);
    }

    public function test_running_the_command_twice_claims_and_dispatches_only_once(): void
    {
        $this->makeInstructor(5000);

        $this->artisan('payouts:run')->assertExitCode(0);
        $this->artisan('payouts:run')->assertExitCode(0);

        $this->assertEquals(1, PayoutBatch::count());
        Queue::assertPushed(SubmitPayoutBatchJob::class, 1);
    }

    public function test_below_minimum_is_skipped_and_nothing_is_sent(): void
    {
        $instructor = $this->makeInstructor(500);

        $this->assertNull($this->claim($instructor));

        $this->assertEquals('skipped', PayoutBatch::first()->status);
        $this->assertCount(0, FakePaymentGateway::$transfers);
        $this->assertEquals(0, LedgerEntry::where('entry_type', 'payout')->count());
    }

    public function test_instructor_without_payout_account_is_not_paid(): void
    {
        $instructor = $this->makeInstructor(5000, withAccount: false);

        $this->assertNull($this->claim($instructor));
        $this->assertEquals(0, PayoutBatch::count());
    }

    public function test_only_one_in_flight_batch_per_instructor(): void
    {
        $instructor = $this->makeInstructor(5000);

        $first = $this->claim($instructor, Carbon::yesterday());
        $this->assertNotNull($first);

        // First is still 'pending' (in flight). A later period must not be claimable.
        $this->assertNull($this->claim($instructor, Carbon::today()));
        $this->assertEquals(1, PayoutBatch::count());
    }

    public function test_explicit_decline_marks_failed_and_money_stays_owed(): void
    {
        FakePaymentGateway::$transferMode = 'decline';
        $instructor = $this->makeInstructor(5000);

        $batch = $this->claim($instructor);
        app(SubmitPayoutBatchAction::class)->execute($batch);

        $batch->refresh();
        $this->assertEquals('failed', $batch->status);
        $this->assertNotNull($batch->failure_reason);
        $this->assertEquals(0, LedgerEntry::where('entry_type', 'payout')->count());
        $this->assertEquals(0, InstructorBalance::find($instructor->id)->paid_cents);
    }

    public function test_timeout_is_uncertain_never_failed_nor_succeeded_and_reconcile_is_scheduled(): void
    {
        FakePaymentGateway::$transferMode = 'timeout_not_created';
        $instructor = $this->makeInstructor(5000);

        $batch = $this->claim($instructor);
        app(SubmitPayoutBatchAction::class)->execute($batch);

        $batch->refresh();
        $this->assertEquals('uncertain', $batch->status);
        $this->assertEquals(1, $batch->uncertain_count);
        $this->assertEquals(0, LedgerEntry::where('entry_type', 'payout')->count()); // "paid" never recorded speculatively
        Queue::assertPushed(ReconcilePayoutBatchJob::class);
    }

    public function test_reconcile_finds_transfer_that_landed_without_creating_a_second_one(): void
    {
        FakePaymentGateway::$transferMode = 'timeout_created';
        $instructor = $this->makeInstructor(5000);

        $batch = $this->claim($instructor);
        app(SubmitPayoutBatchAction::class)->execute($batch);
        $this->assertEquals('uncertain', $batch->fresh()->status);

        app(ReconcilePayoutBatchAction::class)->execute($batch->id);

        $batch->refresh();
        $this->assertEquals('succeeded', $batch->status);
        $this->assertCount(1, FakePaymentGateway::$transfers); // still exactly one transfer
        $this->assertEquals(-5000, LedgerEntry::where('entry_type', 'payout')->value('amount_cents'));
    }

    public function test_reconcile_resends_with_same_key_when_no_transfer_exists(): void
    {
        FakePaymentGateway::$transferMode = 'timeout_not_created';
        $instructor = $this->makeInstructor(5000);

        $batch = $this->claim($instructor);
        $keyBefore = $batch->gateway_idempotency_key;
        app(SubmitPayoutBatchAction::class)->execute($batch);
        $this->assertCount(0, FakePaymentGateway::$transfers);

        FakePaymentGateway::$transferMode = 'success'; // provider recovered
        app(ReconcilePayoutBatchAction::class)->execute($batch->id);

        $batch->refresh();
        $this->assertEquals('succeeded', $batch->status);
        $this->assertEquals($keyBefore, $batch->gateway_idempotency_key); // never regenerated
        $this->assertArrayHasKey($keyBefore, FakePaymentGateway::$transfers);
        $this->assertCount(1, FakePaymentGateway::$transfers);
    }

    public function test_finalizing_twice_does_not_double_debit(): void
    {
        $instructor = $this->makeInstructor(5000);
        $batch = $this->claim($instructor);
        app(SubmitPayoutBatchAction::class)->execute($batch);

        $finalize = app(FinalizePayoutBatchAction::class);
        $finalize->markSucceeded($batch->id, 'tr_again');
        $finalize->markSucceeded($batch->id, 'tr_again');

        $this->assertEquals(1, LedgerEntry::where('entry_type', 'payout')->count());
        $this->assertEquals(-5000, InstructorBalance::find($instructor->id)->paid_cents); // not -10000
    }

    public function test_payout_entries_are_reflected_when_balances_are_rebuilt(): void
    {
        $instructor = $this->makeInstructor(5000);
        $batch = $this->claim($instructor);
        app(SubmitPayoutBatchAction::class)->execute($batch);

        DB::table('instructor_balances')->where('instructor_id', $instructor->id)->update(['paid_cents' => 0]); // drift

        $this->artisan('ledger:rebuild-balances')->assertExitCode(0);

        $this->assertEquals(-5000, InstructorBalance::find($instructor->id)->paid_cents);
    }
}
