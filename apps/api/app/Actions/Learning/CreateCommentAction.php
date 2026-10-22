<?php

namespace App\Actions\Learning;

use App\Models\Comment;
use App\Models\Lesson;
use App\Models\User;
use App\Notifications\CommentReplyNotification;
use App\Support\SafeNotify;
use Illuminate\Validation\ValidationException;

class CreateCommentAction
{
    /**
     * The caller is responsible for authorization (CommentPolicy::create). This
     * action enforces the thread rules: a reply must target a live, top-level
     * comment on the SAME lesson, so threads stay two levels deep. A reply notifies
     * the parent comment's author (never yourself).
     */
    public function execute(User $author, Lesson $lesson, string $body, ?int $parentId = null): Comment
    {
        $parent = null;

        if ($parentId !== null) {
            $parent = Comment::forLesson($lesson)->whereKey($parentId)->first(); // soft-deleted rows are excluded

            if (! $parent) {
                throw ValidationException::withMessages([
                    'parent_id' => ['The comment you are replying to no longer exists on this lesson.'],
                ]);
            }

            if ($parent->parent_id !== null) {
                throw ValidationException::withMessages([
                    'parent_id' => ['Replies can only be added to top-level comments.'],
                ]);
            }
        }

        $comment = Comment::create([
            'commentable_type' => $lesson->getMorphClass(),
            'commentable_id' => $lesson->id,
            'user_id' => $author->id,
            'parent_id' => $parentId,
            'body' => trim($body),
        ]);

        if ($parent && $parent->user_id !== $author->id) {
            $parent->loadMissing('user');

            if ($parent->user) {
                SafeNotify::send($parent->user, CommentReplyNotification::make($comment, $lesson, $author));
            }
        }

        return $comment;
    }
}
