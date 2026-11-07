<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;

class CreateAdminUser extends Command
{
    protected $signature = 'coursewave:create-admin
        {email : The admin\'s email address}
        {--name=Admin : Display name}
        {--password= : At least 12 characters. Omit it to generate one (shown once)}
        {--promote : Give an existing user the admin role instead of failing}';

    protected $description = 'Create an admin account (or promote an existing user). Admins can never self-register, so this is how the first one is made.';

    public function handle(): int
    {
        $email = Str::lower(trim((string) $this->argument('email')));

        if (Validator::make(['email' => $email], ['email' => ['required', 'email:rfc', 'max:255']])->fails()) {
            $this->error("[{$email}] is not a valid email address.");

            return self::FAILURE;
        }

        if (! Role::where('name', 'admin')->where('guard_name', 'web')->exists()) {
            $this->error('The admin role does not exist yet. Run: php artisan db:seed --class=RoleSeeder --force');

            return self::FAILURE;
        }

        $existing = User::where('email', $email)->first();

        if ($existing) {
            if (! $this->option('promote')) {
                $this->error("A user with [{$email}] already exists. Pass --promote to make them an admin.");

                return self::FAILURE;
            }

            // One role per user (project convention): the admin role replaces whatever they had.
            $existing->syncRoles(['admin']);
            $this->info("{$email} is now an admin.");

            return self::SUCCESS;
        }

        $password = (string) $this->option('password');

        if ($password !== '' && strlen($password) < 12) {
            $this->error('The password must be at least 12 characters.');

            return self::FAILURE;
        }

        $generated = $password === '';
        $password = $generated ? Str::password(20, symbols: false) : $password;

        $user = User::create([
            'name' => (string) $this->option('name'),
            'email' => $email,
            'password' => Hash::make($password),
        ]);
        $user->assignRole('admin');

        $this->info("Admin created: {$email}");

        if ($generated) {
            $this->warn("Generated password (shown once, change it after signing in): {$password}");
        }

        return self::SUCCESS;
    }
}
