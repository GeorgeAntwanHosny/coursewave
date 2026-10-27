<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('live_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('course_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->timestamp('starts_at');
            $table->unsignedSmallInteger('duration_minutes');
            $table->timestamp('ends_at'); // derived from starts_at + duration by the model, kept for simple range queries
            $table->string('meeting_url', 2048);
            $table->enum('status', ['scheduled', 'canceled'])->default('scheduled');
            $table->timestamp('canceled_at')->nullable();
            // Set when the reminder has been claimed. The claim is an atomic UPDATE, so it fires exactly once.
            $table->timestamp('reminder_sent_at')->nullable();
            $table->timestamps();

            $table->index(['course_id', 'status', 'starts_at']);
            $table->index(['status', 'reminder_sent_at', 'starts_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('live_sessions');
    }
};
