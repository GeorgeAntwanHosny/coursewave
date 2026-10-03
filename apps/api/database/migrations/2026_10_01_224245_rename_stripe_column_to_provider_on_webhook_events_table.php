<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('webhook_events', function (Blueprint $table) {
            $table->string('provider')->default('stripe')->after('id');
            $table->string('provider_event_id')->nullable()->after('provider');
        });

        DB::table('webhook_events')->orderBy('id')->chunk(500, function ($rows) {
            foreach ($rows as $row) {
                DB::table('webhook_events')->where('id', $row->id)->update([
                    'provider_event_id' => $row->stripe_event_id,
                ]);
            }
        });

        Schema::table('webhook_events', function (Blueprint $table) {
            $table->dropUnique('webhook_events_stripe_event_id_unique');
            $table->dropColumn('stripe_event_id');
            // Composite, not a plain unique on provider_event_id alone — different
            // providers are free to use overlapping event-id formats/values.
            $table->unique(['provider', 'provider_event_id']);
        });
    }

    public function down(): void
    {
        Schema::table('webhook_events', function (Blueprint $table) {
            $table->string('stripe_event_id')->nullable()->unique();
        });

        DB::table('webhook_events')->orderBy('id')->chunk(500, function ($rows) {
            foreach ($rows as $row) {
                DB::table('webhook_events')->where('id', $row->id)->update([
                    'stripe_event_id' => $row->provider_event_id,
                ]);
            }
        });

        Schema::table('webhook_events', function (Blueprint $table) {
            $table->dropUnique(['provider', 'provider_event_id']);
            $table->dropColumn(['provider', 'provider_event_id']);
        });
    }
};
