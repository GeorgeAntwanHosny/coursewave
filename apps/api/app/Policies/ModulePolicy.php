<?php

namespace App\Policies;

use App\Models\Module;
use App\Models\User;

class ModulePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasRole(['instructor', 'admin']);
    }

    public function view(User $user, Module $module): bool
    {
        return $user->hasRole('admin') || $module->course->instructor_id === $user->id;
    }

    public function create(User $user): bool
    {
        return $user->hasRole('instructor');
    }

    public function update(User $user, Module $module): bool
    {
        return $user->hasRole('admin') || $module->course->instructor_id === $user->id;
    }

    public function delete(User $user, Module $module): bool
    {
        return $user->hasRole('admin') || $module->course->instructor_id === $user->id;
    }
}
