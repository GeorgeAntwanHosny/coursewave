<?php

namespace App\Broadcasting;

use App\Models\User;

class UserChannel
{
    /** A user may listen only to their own private notification channel. */
    public function join(User $user, int $id): bool
    {
        return $user->id === $id;
    }
}
