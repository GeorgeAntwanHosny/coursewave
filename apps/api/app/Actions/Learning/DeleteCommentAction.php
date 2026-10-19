<?php

namespace App\Actions\Learning;

use App\Models\Comment;

class DeleteCommentAction
{
    /**
     * Soft delete only. A top-level comment that still has replies is shown as a
     * "deleted" placeholder so other people's replies keep their context.
     */
    public function execute(Comment $comment): void
    {
        $comment->delete();
    }
}
