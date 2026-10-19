<?php

namespace App\Http\Controllers\Api;

use App\Actions\Learning\CreateCommentAction;
use App\Actions\Learning\DeleteCommentAction;
use App\Actions\Learning\UpdateCommentAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Learning\StoreCommentRequest;
use App\Http\Requests\Learning\UpdateCommentRequest;
use App\Http\Resources\CommentResource;
use App\Models\Comment;
use App\Models\Lesson;
use App\Support\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class CommentController extends Controller
{
    public function index(Request $request, Lesson $lesson)
    {
        $lesson->loadMissing('module.course');

        if ($lesson->module->course->status !== 'published') {
            return ApiResponse::error('Lesson not found.', null, 404);
        }

        if (! Gate::allows('viewAny', [Comment::class, $lesson])) {
            return ApiResponse::error('You need an active subscription to join the discussion.', null, 403);
        }

        $instructorId = $lesson->module->course->instructor_id;

        $comments = Comment::withTrashed()
            ->forLesson($lesson)
            ->topLevel()
            // Show a deleted comment only while it still has live replies (the placeholder case).
            ->where(fn ($q) => $q->whereNull('deleted_at')->orWhereHas('replies'))
            ->with(['user', 'replies.user'])
            ->latest()
            ->latest('id')
            ->paginate(10);

        $comments->getCollection()->each(function (Comment $comment) use ($instructorId) {
            $comment->setAttribute('course_instructor_id', $instructorId);
            $comment->replies->each(fn (Comment $reply) => $reply->setAttribute('course_instructor_id', $instructorId));
        });

        return ApiResponse::success([
            'items' => CommentResource::collection($comments->items()),
            'meta' => [
                'current_page' => $comments->currentPage(),
                'last_page' => $comments->lastPage(),
                'total' => $comments->total(),
            ],
        ], 'Comments retrieved.');
    }

    public function store(StoreCommentRequest $request, Lesson $lesson, CreateCommentAction $action)
    {
        $lesson->loadMissing('module.course');

        if ($lesson->module->course->status !== 'published') {
            return ApiResponse::error('Lesson not found.', null, 404);
        }

        if (! Gate::allows('create', [Comment::class, $lesson])) {
            return ApiResponse::error('You need an active subscription to join the discussion.', null, 403);
        }

        try {
            $comment = $action->execute(
                $request->user(),
                $lesson,
                $request->validated('body'),
                $request->validated('parent_id'),
            );
        } catch (ValidationException $e) {
            return ApiResponse::error(collect($e->errors())->flatten()->first(), $e->errors(), 422);
        }

        $comment->load('user');
        $comment->setAttribute('course_instructor_id', $lesson->module->course->instructor_id);

        return ApiResponse::success(new CommentResource($comment), 'Comment posted.', 201);
    }

    public function update(UpdateCommentRequest $request, Comment $comment, UpdateCommentAction $action)
    {
        if (! Gate::allows('update', $comment)) {
            return ApiResponse::error('You can only edit your own comments.', null, 403);
        }

        $comment = $action->execute($comment, $request->validated('body'));
        $comment->load('user');
        $comment->setAttribute('course_instructor_id', $this->courseInstructorId($comment));

        return ApiResponse::success(new CommentResource($comment), 'Comment updated.');
    }

    public function destroy(Comment $comment, DeleteCommentAction $action)
    {
        if (! Gate::allows('delete', $comment)) {
            return ApiResponse::error('You are not allowed to delete this comment.', null, 403);
        }

        $action->execute($comment);

        return ApiResponse::success(null, 'Comment deleted.');
    }

    protected function courseInstructorId(Comment $comment): ?int
    {
        $comment->loadMissing('commentable.module.course');
        $lesson = $comment->commentable;

        return $lesson instanceof Lesson ? $lesson->module->course->instructor_id : null;
    }
}
