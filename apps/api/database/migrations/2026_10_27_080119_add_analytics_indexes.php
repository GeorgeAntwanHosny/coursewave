<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('CREATE INDEX IF NOT EXISTS ledger_entries_type_date_idx ON ledger_entries (entry_type, created_at)');
        DB::statement('CREATE INDEX IF NOT EXISTS consumption_events_instructor_date_idx ON consumption_events (instructor_id, event_date)');
        DB::statement('CREATE INDEX IF NOT EXISTS consumption_events_date_idx ON consumption_events (event_date)');
        DB::statement('CREATE INDEX IF NOT EXISTS lesson_progress_course_status_idx ON lesson_progress (course_id, status)');
        DB::statement('CREATE INDEX IF NOT EXISTS certificates_course_issued_idx ON certificates (course_id, issued_at)');
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('DROP INDEX IF EXISTS ledger_entries_type_date_idx');
        DB::statement('DROP INDEX IF EXISTS consumption_events_instructor_date_idx');
        DB::statement('DROP INDEX IF EXISTS consumption_events_date_idx');
        DB::statement('DROP INDEX IF EXISTS lesson_progress_course_status_idx');
        DB::statement('DROP INDEX IF EXISTS certificates_course_issued_idx');
    }
};
