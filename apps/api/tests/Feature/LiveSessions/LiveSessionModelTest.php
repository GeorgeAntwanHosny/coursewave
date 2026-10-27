<?php

namespace Tests\Feature\LiveSessions;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsLiveSessionFixtures;
use Tests\TestCase;

class LiveSessionModelTest extends TestCase
{
    use BuildsLiveSessionFixtures, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
        config(['coursewave.live_sessions.join_opens_minutes_before' => 15]);
    }

    public function test_ends_at_always_follows_start_and_duration(): void
    {
        $session = $this->sessionFor($this->publishedCourse(), ['starts_at' => now()->addDay()->startOfMinute(), 'duration_minutes' => 45]);
        $this->assertTrue($session->ends_at->equalTo($session->starts_at->copy()->addMinutes(45)));

        $session->update(['duration_minutes' => 120]);
        $this->assertTrue($session->fresh()->ends_at->equalTo($session->starts_at->copy()->addMinutes(120)));
    }

    public function test_the_join_window_opens_before_the_start_and_closes_at_the_end(): void
    {
        $course = $this->publishedCourse();

        $this->assertTrue($this->sessionFor($course, ['starts_at' => now()->addMinutes(14)])->canJoinNow());
        $this->assertFalse($this->sessionFor($course, ['starts_at' => now()->addMinutes(16)])->canJoinNow());
        $this->assertTrue($this->sessionFor($course, ['starts_at' => now()->subMinutes(10), 'duration_minutes' => 60])->canJoinNow());
        $this->assertFalse($this->sessionFor($course, ['starts_at' => now()->subHours(3), 'duration_minutes' => 60])->canJoinNow());
        $this->assertFalse($this->sessionFor($course, ['starts_at' => now()->addMinutes(5), 'status' => 'canceled', 'canceled_at' => now()])->canJoinNow());
    }

    public function test_the_display_status_covers_every_state(): void
    {
        $course = $this->publishedCourse();

        $this->assertSame('upcoming', $this->sessionFor($course, ['starts_at' => now()->addHour()])->displayStatus());
        $this->assertSame('live', $this->sessionFor($course, ['starts_at' => now()->subMinutes(5), 'duration_minutes' => 60])->displayStatus());
        $this->assertSame('ended', $this->sessionFor($course, ['starts_at' => now()->subHours(3), 'duration_minutes' => 60])->displayStatus());
        $this->assertSame('canceled', $this->sessionFor($course, ['status' => 'canceled', 'canceled_at' => now()])->displayStatus());
    }

    public function test_only_https_links_are_considered_safe(): void
    {
        $session = $this->sessionFor($this->publishedCourse());

        $this->assertSame($session->meeting_url, $session->safeMeetingUrl());

        $session->meeting_url = 'http://insecure.example.com';
        $this->assertNull($session->safeMeetingUrl());

        $session->meeting_url = 'javascript:alert(1)';
        $this->assertNull($session->safeMeetingUrl());
    }
}
