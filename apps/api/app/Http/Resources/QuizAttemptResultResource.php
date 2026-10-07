<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class QuizAttemptResultResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $this->loadMissing('answers.question.options', 'answers.selectedOption');

        return [
            'id' => $this->id,
            'score_percent' => $this->score_percent,
            'correct_count' => $this->correct_count,
            'total_questions' => $this->total_questions,
            'submitted_at' => $this->submitted_at->toIso8601String(),
            'answers' => $this->answers->map(fn ($answer) => [
                'question_id' => $answer->quiz_question_id,
                'question' => $answer->question->question,
                'selected_option_id' => $answer->quiz_option_id,
                'is_correct' => $answer->is_correct,
                'options' => $answer->question->options->map(fn ($o) => [
                    'id' => $o->id,
                    'option_text' => $o->option_text,
                    'is_correct' => $o->is_correct,
                ]),
            ]),
        ];
    }
}
