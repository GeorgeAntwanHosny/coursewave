<?php

namespace Tests\Feature\Notifications;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class NotificationApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Role::firstOrCreate(['name' => 'student', 'guard_name' => 'web']);
    }

    protected function actingWith(string $token): static
    {
        $this->app['auth']->forgetGuards(); // convention #37

        return $this->withHeader('Authorization', "Bearer {$token}");
    }

    /** @return array{0: User, 1: string} */
    protected function user(): array
    {
        $user = User::factory()->create();
        $user->assignRole('student');

        return [$user, $user->createToken('test')->plainTextToken];
    }

    /** Created directly so the API tests don't depend on queues or broadcasting. */
    protected function makeNotification(User $user, array $data = [], bool $read = false, $createdAt = null): DatabaseNotification
    {
        return $user->notifications()->create([
            'id' => (string) Str::uuid(),
            'type' => 'App\\Notifications\\SystemTestNotification',
            'data' => array_merge(['kind' => 'system_test', 'title' => 'Hello', 'body' => 'World', 'url' => '/dashboard'], $data),
            'read_at' => $read ? now() : null,
            'created_at' => $createdAt ?? now(),
        ]);
    }

    public function test_guests_are_unauthenticated(): void
    {
        $this->getJson('/api/notifications')->assertStatus(401);
        $this->getJson('/api/notifications/unread-count')->assertStatus(401);
        $this->postJson('/api/notifications/read-all')->assertStatus(401);
    }

    public function test_list_returns_only_my_notifications_newest_first_with_the_unread_count(): void
    {
        [$me, $token] = $this->user();
        [$other] = $this->user();

        $this->makeNotification($me, ['title' => 'Old'], read: true, createdAt: now()->subMinutes(10));
        $this->makeNotification($me, ['title' => 'New'], createdAt: now()->subMinute());
        $this->makeNotification($other, ['title' => 'Not mine']);

        $this->actingWith($token)->getJson('/api/notifications')
            ->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.meta.total', 2)
            ->assertJsonPath('data.unread_count', 1)
            ->assertJsonPath('data.items.0.title', 'New')
            ->assertJsonPath('data.items.0.is_read', false)
            ->assertJsonPath('data.items.0.kind', 'system_test')
            ->assertJsonPath('data.items.0.url', '/dashboard')
            ->assertJsonPath('data.items.1.title', 'Old')
            ->assertJsonPath('data.items.1.is_read', true);
    }

    public function test_list_is_paginated(): void
    {
        [$me, $token] = $this->user();
        foreach (range(1, 16) as $i) {
            $this->makeNotification($me, ['title' => "N{$i}"], createdAt: now()->subMinutes(20 - $i));
        }

        $this->actingWith($token)->getJson('/api/notifications')
            ->assertJsonCount(15, 'data.items')
            ->assertJsonPath('data.meta.last_page', 2)
            ->assertJsonPath('data.items.0.title', 'N16');

        $this->actingWith($token)->getJson('/api/notifications?page=2')->assertJsonCount(1, 'data.items');
    }

    public function test_unread_filter_and_unread_count(): void
    {
        [$me, $token] = $this->user();
        $this->makeNotification($me, read: true);
        $this->makeNotification($me);
        $this->makeNotification($me);

        $this->actingWith($token)->getJson('/api/notifications?unread=1')
            ->assertJsonCount(2, 'data.items')
            ->assertJsonPath('data.unread_count', 2);

        $this->actingWith($token)->getJson('/api/notifications/unread-count')
            ->assertStatus(200)
            ->assertJsonPath('data.unread_count', 2);
    }

    public function test_marking_one_read_is_idempotent_and_keeps_the_original_read_time(): void
    {
        [$me, $token] = $this->user();
        $notification = $this->makeNotification($me);

        $this->actingWith($token)->postJson("/api/notifications/{$notification->id}/read")
            ->assertStatus(200)
            ->assertJsonPath('data.is_read', true);

        $firstReadAt = $notification->fresh()->read_at;
        $this->assertNotNull($firstReadAt);

        $this->travel(5)->minutes();

        $this->actingWith($token)->postJson("/api/notifications/{$notification->id}/read")->assertStatus(200);
        $this->assertTrue($firstReadAt->equalTo($notification->fresh()->read_at));

        $this->actingWith($token)->getJson('/api/notifications/unread-count')->assertJsonPath('data.unread_count', 0);
    }

    public function test_another_users_notification_is_not_found_and_stays_unread(): void
    {
        [$me, $myToken] = $this->user();
        [$other] = $this->user();
        $theirs = $this->makeNotification($other);

        $this->actingWith($myToken)->postJson("/api/notifications/{$theirs->id}/read")
            ->assertStatus(404)
            ->assertJsonPath('success', false);

        $this->assertNull($theirs->fresh()->read_at);
    }

    public function test_a_malformed_id_is_not_found_instead_of_a_server_error(): void
    {
        [, $token] = $this->user();

        $this->actingWith($token)->postJson('/api/notifications/not-a-uuid/read')->assertStatus(404);
    }

    public function test_mark_all_read_only_touches_my_notifications(): void
    {
        [$me, $myToken] = $this->user();
        [$other] = $this->user();
        $this->makeNotification($me);
        $this->makeNotification($me);
        $theirs = $this->makeNotification($other);

        $this->actingWith($myToken)->postJson('/api/notifications/read-all')
            ->assertStatus(200)
            ->assertJsonPath('data.marked', 2);

        $this->assertEquals(0, $me->unreadNotifications()->count());
        $this->assertNull($theirs->fresh()->read_at);
    }
}
