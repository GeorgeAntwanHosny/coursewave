<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class ModuleFactory extends Factory
{
    public function definition(): array
    {
        return ['title' => $this->faker->words(3, true), 'order' => 0];
    }
}
