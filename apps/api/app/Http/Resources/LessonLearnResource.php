<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LessonLearnResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'type' => $this->type,
            'video_url' => $this->video_url,
            'video_duration_seconds' => $this->video_duration_seconds,
            'body' => $this->body,
            'order' => $this->order,
            'course' => [
                'id' => $this->module->course->id,
                'title' => $this->module->course->title,
                'slug' => $this->module->course->slug,
            ],
            'module' => [
                'id' => $this->module->id,
                'title' => $this->module->title,
            ],
            'resource_links' => $this->resourceLinks->map(fn ($link) => [
                'id' => $link->id,
                'title' => $link->title,
                'url' => $link->url,
            ]),
            'has_quiz' => $this->quiz()->exists(),
            'quiz_id' => $this->quiz?->id,
            'progress' => [
                'status' => $this->my_progress->status ?? 'not_started',
                'watched_seconds' => $this->my_progress->watched_seconds ?? 0,
            ],
            'previous_lesson_id' => $this->previous_lesson_id,
            'next_lesson_id' => $this->next_lesson_id,
        ];
    }
}
