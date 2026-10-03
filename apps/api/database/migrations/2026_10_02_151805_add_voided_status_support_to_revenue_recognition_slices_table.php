<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('revenue_recognition_slices', function (Blueprint $table) {
            $table->timestamp('voided_at')->nullable()->after('recognized_at');
        });

        DB::statement('ALTER TABLE revenue_recognition_slices DROP CONSTRAINT IF EXISTS revenue_recognition_slices_status_check');
    }

    public function down(): void
    {
        DB::statement("DELETE FROM revenue_recognition_slices WHERE status = 'voided'");
        DB::statement("ALTER TABLE revenue_recognition_slices ADD CONSTRAINT revenue_recognition_slices_status_check CHECK (status IN ('pending','recognized'))");

        Schema::table('revenue_recognition_slices', function (Blueprint $table) {
            $table->dropColumn('voided_at');
        });
    }
};
