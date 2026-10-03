<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('plan_id')->constrained()->restrictOnDelete();
            $table->enum('status', ['pending', 'active', 'canceled', 'expired'])->default('pending');
            $table->string('stripe_checkout_session_id')->unique()->nullable(); // idempotency key for confirm
            $table->string('stripe_payment_intent_id')->nullable();
            $table->bigInteger('total_amount_cents'); // snapshot at purchase time, never recomputed from plan later
            $table->unsignedInteger('term_days'); // snapshot too — plan.term_days could change after purchase
            $table->date('starts_at')->nullable();
            $table->date('ends_at')->nullable();
            $table->timestamp('canceled_at')->nullable();
            $table->timestamps();

            $table->index(['student_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subscriptions');
    }
};
