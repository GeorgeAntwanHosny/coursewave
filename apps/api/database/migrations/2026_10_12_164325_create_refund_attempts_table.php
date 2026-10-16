<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('refund_attempts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('refund_id')->constrained('refunds')->restrictOnDelete();
            $table->unsignedInteger('attempt_number');
            $table->string('operation', 30);   // refund_initial | refund_resend | lookup
            $table->string('outcome', 30);     // succeeded | declined | uncertain | found | not_found | lookup_failed
            $table->string('status_before', 20)->nullable();
            $table->string('status_after', 20)->nullable();
            $table->string('provider');
            $table->string('idempotency_key');
            $table->bigInteger('amount_cents');
            $table->string('provider_refund_id')->nullable();
            $table->unsignedSmallInteger('http_status')->nullable();
            $table->string('provider_request_id')->nullable();
            $table->string('error_code')->nullable();
            $table->text('error_message')->nullable();
            $table->jsonb('response')->nullable();
            $table->unsignedInteger('duration_ms')->nullable();
            $table->timestamp('created_at')->useCurrent(); // append-only: no updated_at

            $table->unique(['refund_id', 'attempt_number']);
            $table->index(['refund_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('refund_attempts');
    }
};
