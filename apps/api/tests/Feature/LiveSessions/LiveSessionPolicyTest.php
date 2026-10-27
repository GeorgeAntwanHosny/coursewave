<?php

namespace Tests\Feature\LiveSessions;

use App\Models\LiveSession;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsLiveSessionFixtures;
use Tests\TestCase;

class LiveSessionPolicyTest extends TestCase
{
    use BuildsLiveSessionFixtures, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
    }

    public function test_only_instructors_can_create(): void
    {
        $this->assertTrue($this->userWithRole('instructor')->can('create', LiveSession::class));
        $this->assertFalse($this->userWithRole('student')->can('create', LiveSession::class));
        $this->assertFalse($this->userWithRole('admin')->can('create', LiveSession::class));
    }

    public function test_only_the_courses_own_instructor_can_edit_and_only_while_open(): void
    {
        $owner = $this->userWithRole('instructor');
        $course = $this->publishedCourse($owner);
        $upcoming = $this->sessionFor($course);
        $live = $this->sessionFor($course, ['starts_at' => now()->subMinutes(10), 'duration_minutes' => 60]);
        $ended = $this->sessionFor($course, ['starts_at' => now()->subHours(3), 'duration_minutes' => 60]);
        $canceled = $this->sessionFor($course, ['status' => 'canceled', 'canceled_at' => now()]);

        $this->assertTrue($owner->can('update', $upcoming));
        $this->assertTrue($owner->can('update', $live));
        $this->assertFalse($owner->can('update', $ended));
        $this->assertFalse($owner->can('update', $canceled));

        $this->assertFalse($this->userWithRole('instructor')->can('update', $upcoming)); // another instructor
        $this->assertFalse($this->userWithRole('admin')->can('update', $upcoming));      // admins moderate, they don't author
        $this->assertFalse($this->userWithRole('student')->can('update', $upcoming));
    }

    public function test_the_owner_and_admins_can_cancel_but_nobody_else(): void
    {
        $owner = $this->userWithRole('instructor');
        $course = $this->publishedCourse($owner);
        $upcoming = $this->sessionFor($course);
        $canceled = $this->sessionFor($course, ['status' => 'canceled', 'canceled_at' => now()]);
        $ended = $this->sessionFor($course, ['starts_at' => now()->subHours(3), 'duration_minutes' => 60]);

        $this->assertTrue($owner->can('cancel', $upcoming));
        $this->assertTrue($this->userWithRole('admin')->can('cancel', $upcoming));
        $this->assertFalse($this->userWithRole('instructor')->can('cancel', $upcoming));
        $this->assertFalse($this->userWithRole('student')->can('cancel', $upcoming));

        $this->assertFalse($owner->can('cancel', $canceled));
        $this->assertFalse($owner->can('cancel', $ended));
    }
}
