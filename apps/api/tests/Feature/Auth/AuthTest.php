<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        foreach (['student', 'instructor', 'admin'] as $role) {
            Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);
        }
    }

    public function test_a_student_can_register(): void
    {
        $response = $this->postJson('/api/register', [
            'name' => 'Jane Student',
            'email' => 'jane@example.com',
            'password' => 'Password123',
            'password_confirmation' => 'Password123',
            'role' => 'student',
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.role', 'student');

        $this->assertDatabaseHas('users', ['email' => 'jane@example.com']);
    }

    public function test_registration_fails_with_duplicate_email(): void
    {
        User::factory()->create(['email' => 'taken@example.com']);

        $response = $this->postJson('/api/register', [
            'name' => 'Dup User',
            'email' => 'taken@example.com',
            'password' => 'Password123',
            'password_confirmation' => 'Password123',
            'role' => 'student',
        ]);

        $response->assertStatus(422)->assertJsonPath('success', false);
    }

    public function test_a_user_can_login_and_receive_a_token(): void
    {
        $user = User::factory()->create(['password' => bcrypt('Password123')]);
        $user->assignRole('student');

        $response = $this->postJson('/api/login', [
            'email' => $user->email,
            'password' => 'Password123',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonStructure(['data' => ['user', 'token']]);
    }

    public function test_login_fails_with_wrong_password(): void
    {
        $user = User::factory()->create(['password' => bcrypt('Password123')]);

        $response = $this->postJson('/api/login', [
            'email' => $user->email,
            'password' => 'WrongPassword',
        ]);

        $response->assertStatus(422)->assertJsonPath('success', false);
    }

    public function test_authenticated_user_can_fetch_me(): void
    {
        $user = User::factory()->create();
        $user->assignRole('instructor');
        $token = $user->createToken('test')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/me');

        $response->assertStatus(200)->assertJsonPath('data.role', 'instructor');
    }

    public function test_unauthenticated_user_cannot_fetch_me(): void
    {
        $this->getJson('/api/me')->assertStatus(401);
    }

    public function test_authenticated_user_can_logout(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('test')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/logout');

        $response->assertStatus(200)->assertJsonPath('success', true);
    }
}
