<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class QuizTakeResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'lesson_id' => $this->lesson_id,
            'questions' => $this->questions->map(fn ($q) => [
                'id' => $q->id,
                'question' => $q->question,
                'options' => $q->options->map(fn ($o) => [
                    'id' => $o->id,
                    'option_text' => $o->option_text,
                    // is_correct intentionally omitted — never sent before submission
                ]),
            ]),
            'best_attempt' => $this->when(
                isset($this->bestAttempt),
                fn () => $this->bestAttempt ? [
                    'score_percent' => $this->bestAttempt->score_percent,
                    'correct_count' => $this->bestAttempt->correct_count,
                    'total_questions' => $this->bestAttempt->total_questions,
                    'submitted_at' => $this->bestAttempt->submitted_at?->toIso8601String(),
                ] : null
            ),
            'attempt_count' => $this->when(isset($this->attemptCount), fn () => $this->attemptCount),
        ];
    }
}
