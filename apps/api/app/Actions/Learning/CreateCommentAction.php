<?php

namespace App\Actions\Learning;

use App\Models\Comment;
use App\Models\Lesson;
use App\Models\User;
use Illuminate\Validation\ValidationException;

class CreateCommentAction
{
    /**
     * The caller is responsible for authorization (CommentPolicy::create). This
     * action enforces the thread rules: a reply must target a live, top-level
     * comment on the SAME lesson, so threads stay two levels deep.
     */
    public function execute(User $author, Lesson $lesson, string $body, ?int $parentId = null): Comment
    {
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

        // Phase 10 hook: notify the parent comment's author of the reply (after commit).

        return $comment;
    }
}
