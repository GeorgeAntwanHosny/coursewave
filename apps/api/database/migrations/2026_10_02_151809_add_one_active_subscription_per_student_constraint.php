<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Partial unique index: a student may have many canceled/expired
        // subscriptions in history, but never more than one 'active' one at
        // a time. This is the actual enforcement — not just application
        // logic that could be raced or bypassed.
        DB::statement(
            "CREATE UNIQUE INDEX subscriptions_one_active_per_student ON subscriptions (student_id) WHERE status = 'active'"
        );
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS subscriptions_one_active_per_student');
    }
};
