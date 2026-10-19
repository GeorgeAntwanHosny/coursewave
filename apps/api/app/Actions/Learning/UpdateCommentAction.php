<?php

namespace App\Actions\Learning;

use App\Models\Comment;

class UpdateCommentAction
{
    public function execute(Comment $comment, string $body): Comment
    {
        $body = trim($body);

        if ($body !== $comment->body) {
            $comment->update(['body' => $body, 'edited_at' => now()]);
        }

        return $comment;
    }
}
