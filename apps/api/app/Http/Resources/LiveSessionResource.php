<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LiveSessionResource extends JsonResource
{
    /** Expects the course and its instructor to be eager-loaded. The raw meeting_url is never serialized. */
    public function toArray(Request $request): array
    {
        $link = $this->safeMeetingUrl();
        $canJoin = $this->canJoinNow() && $link !== null;

        return [
            'id' => $this->id,
            'title' => $this->title,
            'description' => $this->description,
            'course' => [
                'id' => $this->course->id,
                'title' => $this->course->title,
                'slug' => $this->course->slug,
            ],
            'instructor_name' => $this->course->instructor->name,
            'starts_at' => $this->starts_at->toIso8601String(),
            'ends_at' => $this->ends_at->toIso8601String(),
            'duration_minutes' => $this->duration_minutes,
            'status' => $this->displayStatus(), // upcoming | live (ended and canceled are never listed)
            'join_opens_at' => $this->joinOpensAt()->toIso8601String(),
            'can_join' => $canJoin,
            'join_url' => $canJoin ? $link : null, // revealed only inside the join window, only if it is https
        ];
    }
}
