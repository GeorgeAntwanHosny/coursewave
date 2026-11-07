<?php

namespace Tests\Feature\Console;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class CreateAdminUserTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        foreach (['student', 'instructor', 'admin'] as $role) {
            Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);
        }
    }

    public function test_it_creates_an_admin_with_a_generated_password_shown_once(): void
    {
        $this->artisan('coursewave:create-admin', ['email' => 'Boss@Example.com', '--name' => 'The Boss'])
            ->expectsOutputToContain('Generated password')
            ->assertExitCode(0);

        $user = User::where('email', 'boss@example.com')->firstOrFail(); // lowercased
        $this->assertSame('The Boss', $user->name);
        $this->assertTrue($user->hasRole('admin'));
        $this->assertSame(1, $user->roles()->count());
    }

    public function test_an_explicit_password_is_used_and_not_echoed(): void
    {
        $this->artisan('coursewave:create-admin', ['email' => 'boss@example.com', '--password' => 'a-long-enough-password'])
            ->doesntExpectOutputToContain('a-long-enough-password')
            ->assertExitCode(0);

        $this->assertTrue(Hash::check('a-long-enough-password', User::where('email', 'boss@example.com')->firstOrFail()->password));
    }

    public function test_a_short_password_is_rejected_and_nothing_is_created(): void
    {
        $this->artisan('coursewave:create-admin', ['email' => 'boss@example.com', '--password' => 'short'])
            ->expectsOutputToContain('at least 12 characters')
            ->assertExitCode(1);

        $this->assertSame(0, User::where('email', 'boss@example.com')->count());
    }

    public function test_an_invalid_email_is_rejected(): void
    {
        $this->artisan('coursewave:create-admin', ['email' => 'not-an-email'])
            ->expectsOutputToContain('not a valid email')
            ->assertExitCode(1);

        $this->assertSame(0, User::count());
    }

    public function test_an_existing_user_is_not_touched_without_promote(): void
    {
        $student = User::factory()->create(['email' => 'sam@example.com']);
        $student->assignRole('student');

        $this->artisan('coursewave:create-admin', ['email' => 'sam@example.com'])
            ->expectsOutputToContain('already exists')
            ->assertExitCode(1);

        $this->assertTrue($student->fresh()->hasRole('student'));
        $this->assertFalse($student->fresh()->hasRole('admin'));
    }

    public function test_promote_replaces_the_existing_role_so_a_user_still_has_exactly_one(): void
    {
        $student = User::factory()->create(['email' => 'sam@example.com']);
        $student->assignRole('student');

        $this->artisan('coursewave:create-admin', ['email' => 'sam@example.com', '--promote' => true])
            ->assertExitCode(0);

        $student = $student->fresh();
        $this->assertTrue($student->hasRole('admin'));
        $this->assertFalse($student->hasRole('student'));
        $this->assertSame(1, $student->roles()->count());
    }

    public function test_it_fails_with_a_hint_when_the_roles_have_not_been_seeded(): void
    {
        Role::query()->delete();

        $this->artisan('coursewave:create-admin', ['email' => 'boss@example.com'])
            ->expectsOutputToContain('RoleSeeder')
            ->assertExitCode(1);

        $this->assertSame(0, User::count());
    }
}
