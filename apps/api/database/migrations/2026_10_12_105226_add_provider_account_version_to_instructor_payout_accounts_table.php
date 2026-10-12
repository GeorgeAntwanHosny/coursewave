<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('instructor_payout_accounts', function (Blueprint $table) {
            // Opaque to shared code: only the gateway interprets it ('v1' | 'v2' for Stripe).
            // Existing rows were created through the v1 API, hence the default.
            $table->string('provider_account_version', 10)->default('v1')->after('provider_account_id');
        });
    }

    public function down(): void
    {
        Schema::table('instructor_payout_accounts', function (Blueprint $table) {
            $table->dropColumn('provider_account_version');
        });
    }
};
