<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        $roles = ['student', 'instructor', 'admin'];

        foreach ($roles as $role) {
            for ($i = 1; $i <= 3; $i++) {
                $user = User::firstOrCreate(
                    ['email' => "{$role}@llm.com"],
                    [
                        'name' => ucfirst($role) . " User",
                        'password' => bcrypt("{$role}@12334"),
                    ]
                );

                $user->assignRole($role);
            }
        }
    }
}
