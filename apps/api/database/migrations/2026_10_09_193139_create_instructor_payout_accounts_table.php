<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('instructor_payout_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('instructor_id')->unique()->constrained('users')->restrictOnDelete();
            $table->string('provider');               // 'stripe' — never stripe_* column names (convention #18)
            $table->string('provider_account_id');    // e.g. Stripe Connect acct_...
            $table->boolean('payouts_enabled')->default(false);
            $table->timestamps();

            $table->unique(['provider', 'provider_account_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('instructor_payout_accounts');
    }
};
