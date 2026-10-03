<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('revenue_recognition_slices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('subscription_id')->constrained()->cascadeOnDelete();
            $table->date('slice_date');
            $table->bigInteger('amount_cents');
            $table->enum('status', ['pending', 'recognized'])->default('pending');
            $table->timestamp('recognized_at')->nullable();
            $table->timestamps();

            $table->unique(['subscription_id', 'slice_date']); // idempotency guard
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('revenue_recognition_slices');
    }
};
