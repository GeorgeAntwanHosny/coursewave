<?php

namespace App\Notifications;

use App\Models\Comment;
use App\Models\Lesson;
use App\Models\User;
use Illuminate\Support\Str;

final class CommentReplyNotification extends AppNotification
{
    public static function make(Comment $reply, Lesson $lesson, User $author): self
    {
        $lesson->loadMissing('module.course');

        return new self(
            'comment_reply',
            "{$author->name} replied to your comment",
            Str::limit($reply->body, 120),
            "/learn/{$lesson->module->course->slug}/{$lesson->id}",
        );
    }
}
