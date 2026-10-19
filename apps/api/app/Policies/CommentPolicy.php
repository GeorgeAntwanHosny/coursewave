<?php

namespace App\Policies;

use App\Models\Comment;
use App\Models\Lesson;
use App\Models\User;

class CommentPolicy
{
    // Called as Gate::allows('viewAny'|'create', [Comment::class, $lesson]).
    public function viewAny(User $user, Lesson $lesson): bool
    {
        return $this->canParticipate($user, $lesson);
    }

    public function create(User $user, Lesson $lesson): bool
    {
        return $this->canParticipate($user, $lesson);
    }

    /** Only the author edits their own comment — not even the course instructor or an admin. */
    public function update(User $user, Comment $comment): bool
    {
        return ! $comment->trashed() && $comment->user_id === $user->id;
    }

    /** Moderation: the author, the course's instructor, or an admin. */
    public function delete(User $user, Comment $comment): bool
    {
        if ($comment->trashed()) {
            return false;
        }

        if ($comment->user_id === $user->id || $user->hasRole('admin')) {
            return true;
        }

        $comment->loadMissing('commentable.module.course');
        $lesson = $comment->commentable;

        return $lesson instanceof Lesson && $lesson->module->course->instructor_id === $user->id;
    }

    protected function canParticipate(User $user, Lesson $lesson): bool
    {
        $lesson->loadMissing('module.course');
        $course = $lesson->module->course;

        if ($user->hasRole('admin') || $course->instructor_id === $user->id) {
            return true;
        }

        return $course->status === 'published'
            && $user->subscriptions()->where('status', 'active')->exists();
    }
}
