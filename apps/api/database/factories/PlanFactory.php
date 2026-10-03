<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class PlanFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => 'Monthly',
            'slug' => 'monthly-' . $this->faker->unique()->numberBetween(1000, 9999),
            'billing_interval' => 'monthly',
            'term_days' => 30,
            'price_cents' => 1999,
            'is_active' => true,
        ];
    }
}
