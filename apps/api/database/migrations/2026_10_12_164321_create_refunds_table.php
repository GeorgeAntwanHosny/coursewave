<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('refunds', function (Blueprint $table) {
            $table->id();
            // One refund per subscription (derives the key from the subscription id).
            $table->foreignId('subscription_id')->unique()->constrained()->restrictOnDelete();
            $table->foreignId('student_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('requested_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('provider');
            $table->string('provider_payment_id')->nullable(); // snapshot of subscriptions.provider_payment_id
            $table->bigInteger('amount_cents');                // = sum of the slices voided at cancellation
            $table->string('reason', 20);                      // plan_switch | student_cancel | admin_cancel
            $table->enum('status', ['pending', 'submitted', 'succeeded', 'failed', 'uncertain', 'reconciling'])
                ->default('pending');
            $table->string('idempotency_key')->unique();       // "refund:{subscription_id}", generated once, never regenerated
            $table->string('provider_refund_id')->nullable();
            $table->string('failure_reason', 500)->nullable();
            $table->unsignedInteger('attempts')->default(0);
            $table->unsignedInteger('uncertain_count')->default(0);
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('refunds');
    }
};
