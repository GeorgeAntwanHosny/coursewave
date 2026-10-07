<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CourseLearnResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'slug' => $this->slug,
            'instructor_name' => $this->instructor->name,
            'modules' => $this->modules->map(fn ($module) => [
                'id' => $module->id,
                'title' => $module->title,
                'lessons' => $module->lessons->map(fn ($lesson) => [
                    'id' => $lesson->id,
                    'title' => $lesson->title,
                    'type' => $lesson->type,
                    'order' => $lesson->order,
                    'has_quiz' => $lesson->quiz()->exists(),
                    'quiz_id' => $lesson->quiz?->id,
                    'status' => $lesson->progress_status,
                ]),
            ]),
        ];
    }
}
