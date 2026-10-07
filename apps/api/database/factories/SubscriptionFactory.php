<?php

namespace Database\Factories;

use App\Models\Plan;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class SubscriptionFactory extends Factory
{
    public function definition(): array
    {
        return [
            'student_id' => User::factory(),
            'plan_id' => Plan::factory(),
            'status' => 'active',
            'provider' => 'stripe',
            'provider_checkout_id' => 'cs_test_' . Str::random(16),
            'total_amount_cents' => 1999,
            'term_days' => 30,
            'starts_at' => now()->toDateString(),
            'ends_at' => now()->addDays(30)->toDateString(),
        ];
    }
}
