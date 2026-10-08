<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('instructor_balances', function (Blueprint $table) {
            $table->foreignId('instructor_id')->primary()->constrained('users')->cascadeOnDelete();
            $table->bigInteger('owed_cents')->default(0);
            $table->bigInteger('paid_cents')->default(0); // accumulates negative — payout entries are negative
            $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('instructor_balances');
    }
};
