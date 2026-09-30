<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class LessonFactory extends Factory
{
    public function definition(): array
    {
        return [
            'title' => $this->faker->sentence(4),
            'type' => 'text',
            'body' => $this->faker->paragraphs(3, true),
            'order' => 0,
        ];
    }
}
