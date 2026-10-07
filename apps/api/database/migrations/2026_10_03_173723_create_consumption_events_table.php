<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('consumption_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('subscription_id')->constrained()->cascadeOnDelete();
            $table->foreignId('student_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('instructor_id')->constrained('users')->cascadeOnDelete(); // denormalized, see README §8
            $table->foreignId('course_id')->constrained()->cascadeOnDelete(); // denormalized
            $table->foreignId('lesson_id')->constrained()->cascadeOnDelete();
            $table->enum('lesson_type', ['video', 'text']); // denormalized snapshot
            $table->date('event_date');
            $table->unsignedInteger('engagement_seconds')->default(0); // common weighting currency
            $table->boolean('completed')->default(false);
            $table->timestamps();

            // this composite index, for daily weight computation.
            $table->index(['subscription_id', 'event_date', 'instructor_id']);
            $table->index(['student_id', 'lesson_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('consumption_events');
    }
};
