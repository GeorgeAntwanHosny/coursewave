<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('comments', function (Blueprint $table) {
            $table->id();
            $table->morphs('commentable'); // commentable_type + commentable_id (+ index)
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('parent_id')->nullable()->constrained('comments')->cascadeOnDelete();
            $table->text('body');
            $table->timestamp('edited_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            // Thread listing: top-level comments of one lesson, newest first.
            $table->index(['commentable_type', 'commentable_id', 'parent_id', 'created_at'], 'comments_thread_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('comments');
    }
};
