<?php

namespace Tests\Concerns;

use App\Models\Course;
use App\Models\Lesson;
use App\Models\LessonProgress;
use App\Models\LiveSession;
use App\Models\Module;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use Spatie\Permission\Models\Role;

trait BuildsLiveSessionFixtures
{
    protected function seedRoles(): void
    {
        foreach (['student', 'instructor', 'admin'] as $role) {
            Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);
        }
    }

    protected function actingWith(string $token): static
    {
        $this->app['auth']->forgetGuards(); // convention #37

        return $this->withHeader('Authorization', "Bearer {$token}");
    }

    protected function userWithRole(string $role, ?string $name = null): User
    {
        $user = User::factory()->create($name ? ['name' => $name] : []);
        $user->assignRole($role);

        return $user;
    }

    protected function publishedCourse(?User $instructor = null, string $status = 'published'): Course
    {
        $instructor ??= $this->userWithRole('instructor', 'Ada Instructor');

        return Course::factory()->create([
            'instructor_id' => $instructor->id,
            'status' => $status,
            'title' => 'Laravel Mastery ' . uniqid(),
        ]);
    }

    protected function subscribe(User $student, string $status = 'active'): void
    {
        Subscription::factory()->create([
            'student_id' => $student->id,
            'plan_id' => Plan::factory()->create()->id,
            'status' => $status,
        ]);
    }

    /** A student with an active subscription who has started (not finished) a lesson of $course. */
    protected function studentTakingCourse(Course $course, string $name = 'Sam Student', bool $subscribed = true): User
    {
        $student = $this->userWithRole('student', $name);

        if ($subscribed) {
            $this->subscribe($student);
        }

        $module = Module::factory()->create(['course_id' => $course->id]);
        $lesson = Lesson::factory()->create(['module_id' => $module->id, 'type' => 'text', 'body' => 'short']);

        LessonProgress::create([
            'student_id' => $student->id,
            'lesson_id' => $lesson->id,
            'course_id' => $course->id,
            'status' => 'in_progress',
            'watched_seconds' => 0,
        ]);

        return $student;
    }

    protected function subscribedStudent(string $name = 'Sam Student'): User
    {
        $student = $this->userWithRole('student', $name);
        $this->subscribe($student);

        return $student;
    }

    protected function tokenFor(User $user): string
    {
        return $user->createToken('test')->plainTextToken;
    }

    protected function sessionFor(Course $course, array $overrides = []): LiveSession
    {
        return LiveSession::factory()->create(array_merge(['course_id' => $course->id], $overrides));
    }
}
