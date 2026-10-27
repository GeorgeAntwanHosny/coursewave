<?php

namespace Database\Factories;

use App\Models\Course;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class LiveSessionFactory extends Factory
{
    public function definition(): array
    {
        return [
            'course_id' => Course::factory()->for(User::factory(), 'instructor'),
            'title' => $this->faker->sentence(3),
            'description' => $this->faker->sentence(),
            'starts_at' => now()->addDay(),
            'duration_minutes' => 60,
            'meeting_url' => 'https://meet.example.com/' . $this->faker->lexify('???-????-???'),
            'status' => 'scheduled',
        ];
    }
}
