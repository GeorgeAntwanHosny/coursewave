<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('certificates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained('users')->cascadeOnDelete();
            // Nullable: if the course is deleted the certificate must stay valid and verifiable.
            $table->foreignId('course_id')->nullable()->constrained('courses')->nullOnDelete();
            $table->string('certificate_number')->unique();
            // Snapshots taken at issue time so later renames/deletions never alter a certificate.
            $table->string('student_name');
            $table->string('course_title');
            $table->string('instructor_name');
            $table->timestamp('issued_at');
            $table->string('pdf_path')->nullable();
            $table->timestamp('pdf_generated_at')->nullable();
            $table->timestamps();

            // Completing twice (or two requests racing) can never issue two certificates.
            $table->unique(['student_id', 'course_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('certificates');
    }
};
