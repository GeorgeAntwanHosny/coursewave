<?php

namespace Database\Seeders;

use App\Models\Plan;
use Illuminate\Database\Seeder;

class PlanSeeder extends Seeder
{
    public function run(): void
    {
        $plans = [
            ['name' => 'Monthly', 'slug' => 'monthly', 'billing_interval' => 'monthly', 'term_days' => 30, 'price_cents' => 1999],
            ['name' => '3-Month', 'slug' => 'quarterly', 'billing_interval' => 'quarterly', 'term_days' => 90, 'price_cents' => 4999],
            ['name' => 'Annual', 'slug' => 'annual', 'billing_interval' => 'annual', 'term_days' => 365, 'price_cents' => 14999],
        ];

        foreach ($plans as $plan) {
            Plan::firstOrCreate(['slug' => $plan['slug']], $plan);
        }
    }
}
