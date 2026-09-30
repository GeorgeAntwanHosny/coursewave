<?php

namespace Tests\Feature\Courses;

use App\Models\Course;
use App\Models\Module;
use App\Models\Lesson;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class CourseCatalogTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Role::firstOrCreate(['name' => 'instructor', 'guard_name' => 'web']);
    }

    public function test_index_returns_only_published_courses(): void
    {
        $instructor = User::factory()->create();
        $instructor->assignRole('instructor');

        Course::factory()->create(['instructor_id' => $instructor->id, 'status' => 'published', 'title' => 'Published One']);
        Course::factory()->create(['instructor_id' => $instructor->id, 'status' => 'draft', 'title' => 'Draft One']);

        $response = $this->getJson('/api/courses');

        $response->assertStatus(200)->assertJsonPath('success', true);
        $titles = collect($response->json('data.items'))->pluck('title');
        $this->assertTrue($titles->contains('Published One'));
        $this->assertFalse($titles->contains('Draft One'));
    }

    public function test_show_returns_course_with_modules_and_lessons(): void
    {
        $instructor = User::factory()->create();
        $instructor->assignRole('instructor');
        $course = Course::factory()->create(['instructor_id' => $instructor->id, 'status' => 'published']);
        $module = Module::factory()->create(['course_id' => $course->id]);
        Lesson::factory()->create(['module_id' => $module->id, 'type' => 'video']);

        $response = $this->getJson("/api/courses/{$course->slug}");

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.slug', $course->slug)
            ->assertJsonCount(1, 'data.modules')
            ->assertJsonCount(1, 'data.modules.0.lessons');
    }

    public function test_show_returns_404_for_draft_course(): void
    {
        $instructor = User::factory()->create();
        $instructor->assignRole('instructor');
        $course = Course::factory()->create(['instructor_id' => $instructor->id, 'status' => 'draft']);

        $response = $this->getJson("/api/courses/{$course->slug}");

        $response->assertStatus(404)->assertJsonPath('success', false);
    }

    public function test_show_returns_404_for_nonexistent_slug(): void
    {
        $this->getJson('/api/courses/does-not-exist')
            ->assertStatus(404)
            ->assertJsonPath('success', false);
    }
}
