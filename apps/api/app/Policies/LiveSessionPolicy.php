<?php

namespace App\Policies;

use App\Models\LiveSession;
use App\Models\User;

class LiveSessionPolicy
{
    public function create(User $user): bool
    {
        return $user->hasRole('instructor');
    }

    /** Only the course's own instructor edits, and only while the session is upcoming or live. */
    public function update(User $user, LiveSession $session): bool
    {
        return $this->ownsCourse($user, $session) && $this->isOpen($session);
    }

    /** The course's instructor or an admin (moderation) may cancel an upcoming or live session. */
    public function cancel(User $user, LiveSession $session): bool
    {
        return ($this->ownsCourse($user, $session) || $user->hasRole('admin')) && $this->isOpen($session);
    }

    protected function ownsCourse(User $user, LiveSession $session): bool
    {
        $session->loadMissing('course');

        return $session->course?->instructor_id === $user->id;
    }

    protected function isOpen(LiveSession $session): bool
    {
        return $session->status === 'scheduled' && $session->ends_at->isFuture();
    }
}
