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

        // Filament lists database notifications with a JSON path (data->format), which
        // PostgreSQL can't evaluate on a text column. Existing rows are valid JSON, so they cast cleanly.
        DB::statement('ALTER TABLE notifications ALTER COLUMN data TYPE json USING data::json');
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('ALTER TABLE notifications ALTER COLUMN data TYPE text USING data::text');
    }
};
