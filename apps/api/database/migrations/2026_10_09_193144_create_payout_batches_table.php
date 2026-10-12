<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payout_batches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('instructor_id')->constrained('users')->restrictOnDelete();
            $table->string('provider');
            $table->string('provider_account_id'); // snapshot at claim time
            $table->date('period_start');
            $table->date('period_end');
            $table->bigInteger('amount_cents')->default(0); // computed once at claim, never recomputed
            $table->enum('status', [
                'claimed', 'pending', 'submitted', 'succeeded',
                'failed', 'uncertain', 'reconciling', 'skipped',
            ])->default('claimed');
            $table->string('gateway_idempotency_key')->unique(); // generated once, never regenerated
            $table->string('provider_transfer_id')->nullable();
            $table->string('failure_reason', 500)->nullable();
            $table->unsignedInteger('attempts')->default(0);
            $table->unsignedInteger('uncertain_count')->default(0);
            $table->timestamp('claimed_at');
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            // The primary defense against double claims.
            $table->unique(['instructor_id', 'period_start', 'period_end']);
            $table->index('status');
        });

        // At most one in-flight batch per instructor, enforced by the DB.
        DB::statement(
            "CREATE UNIQUE INDEX payout_batches_one_inflight_per_instructor ON payout_batches (instructor_id) "
            . "WHERE status IN ('claimed','pending','submitted','uncertain','reconciling')"
        );

        // ledger schema includes payout_batch_id; Phase 5 didn't add it yet.
        DB::statement(
            'ALTER TABLE ledger_entries ADD COLUMN payout_batch_id BIGINT NULL REFERENCES payout_batches(id) ON DELETE RESTRICT'
        );
        DB::statement('CREATE INDEX ledger_entries_payout_batch_idx ON ledger_entries (payout_batch_id)');
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS ledger_entries_payout_batch_idx');
        DB::statement('ALTER TABLE ledger_entries DROP COLUMN IF EXISTS payout_batch_id');
        DB::statement('DROP INDEX IF EXISTS payout_batches_one_inflight_per_instructor');
        Schema::dropIfExists('payout_batches');
    }
};
