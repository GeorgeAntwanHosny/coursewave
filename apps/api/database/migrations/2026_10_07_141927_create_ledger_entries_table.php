<?php

use App\Support\LedgerPartitionManager;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement(<<<SQL
            CREATE TABLE ledger_entries (
                id BIGSERIAL NOT NULL,
                instructor_id BIGINT NULL REFERENCES users(id) ON DELETE RESTRICT,
                entry_type VARCHAR(20) NOT NULL,
                amount_cents BIGINT NOT NULL,
                subscription_id BIGINT NULL REFERENCES subscriptions(id) ON DELETE RESTRICT,
                recognition_slice_id BIGINT NULL REFERENCES revenue_recognition_slices(id) ON DELETE RESTRICT,
                idempotency_key VARCHAR(255) NOT NULL,
                created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
                recorded_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL DEFAULT now(),
                CONSTRAINT ledger_entries_entry_type_check
                    CHECK (entry_type IN ('accrual', 'payout', 'refund_adjustment', 'platform_fee')),
                PRIMARY KEY (id, created_at),
                UNIQUE (idempotency_key, created_at)
            ) PARTITION BY RANGE (created_at)
        SQL);

        DB::statement('CREATE INDEX ledger_entries_instructor_type_created_idx ON ledger_entries (instructor_id, entry_type, created_at)');
        DB::statement('CREATE INDEX ledger_entries_subscription_idx ON ledger_entries (subscription_id)');

        // Seed partitions for last month through 3 months ahead, so local
        // dev / early production has somewhere to write immediately.
        // `php artisan ledger:create-partitions` extends this monthly going
        // forward — see Decisions Log for why the scheduler must run this.
        LedgerPartitionManager::createRange(now()->subMonthNoOverflow(), now()->addMonthsNoOverflow(3));
    }

    public function down(): void
    {
        DB::statement('DROP TABLE IF EXISTS ledger_entries CASCADE');
    }
};
