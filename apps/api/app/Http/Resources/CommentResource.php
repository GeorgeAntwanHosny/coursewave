<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CommentResource extends JsonResource
{
    /**
     * Expects the controller to have set a `course_instructor_id` attribute on the
     * model: that is how is_instructor is derived at query time from the course
     * relationship (never from client input). can_edit / can_delete are UI hints
     * only — CommentPolicy is what actually enforces them.
     */
    public function toArray(Request $request): array
    {
        $viewer = $request->user();
        $deleted = $this->trashed();
        $courseInstructorId = $this->getAttribute('course_instructor_id');
        $isMine = $viewer && $this->user_id === $viewer->id;

        return [
            'id' => $this->id,
            'parent_id' => $this->parent_id,
            'is_deleted' => $deleted,
            'body' => $deleted ? null : $this->body,
            'author' => $deleted ? null : [
                'id' => $this->user->id,
                'name' => $this->user->name,
                'avatar_url' => $this->user->avatarUrl(),
            ],
            'is_instructor' => ! $deleted
                && $courseInstructorId !== null
                && $this->user_id === (int) $courseInstructorId,
            'is_mine' => ! $deleted && $isMine,
            'can_edit' => ! $deleted && $isMine,
            'can_delete' => ! $deleted && $viewer && (
                $isMine
                || ($courseInstructorId !== null && $viewer->id === (int) $courseInstructorId)
                || $viewer->hasRole('admin')
            ),
            'edited' => $this->edited_at !== null,
            'created_at' => $this->created_at?->toIso8601String(),
            'created_at_human' => $this->created_at?->diffForHumans(),
            'replies' => CommentResource::collection($this->whenLoaded('replies')),
        ];
    }
}
