<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->string('provider')->default('stripe')->after('student_id');
            $table->string('provider_checkout_id')->nullable()->after('status');
            $table->string('provider_payment_id')->nullable()->after('provider_checkout_id');
        });

        // Backfill from the old columns before dropping them.
        DB::table('subscriptions')->orderBy('id')->chunk(500, function ($rows) {
            foreach ($rows as $row) {
                DB::table('subscriptions')->where('id', $row->id)->update([
                    'provider_checkout_id' => $row->stripe_checkout_session_id,
                    'provider_payment_id' => $row->stripe_payment_intent_id,
                ]);
            }
        });

        Schema::table('subscriptions', function (Blueprint $table) {
            $table->dropUnique('subscriptions_stripe_checkout_session_id_unique');
            $table->dropColumn(['stripe_checkout_session_id', 'stripe_payment_intent_id']);
            $table->unique('provider_checkout_id');
        });
    }

    public function down(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->string('stripe_checkout_session_id')->nullable()->unique();
            $table->string('stripe_payment_intent_id')->nullable();
        });

        DB::table('subscriptions')->orderBy('id')->chunk(500, function ($rows) {
            foreach ($rows as $row) {
                DB::table('subscriptions')->where('id', $row->id)->update([
                    'stripe_checkout_session_id' => $row->provider_checkout_id,
                    'stripe_payment_intent_id' => $row->provider_payment_id,
                ]);
            }
        });

        Schema::table('subscriptions', function (Blueprint $table) {
            $table->dropUnique(['provider_checkout_id']);
            $table->dropColumn(['provider', 'provider_checkout_id', 'provider_payment_id']);
        });
    }
};
