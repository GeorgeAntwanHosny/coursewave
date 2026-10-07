<?php

namespace App\Actions\Learning;

use App\Models\Quiz;
use App\Models\QuizAnswer;
use App\Models\QuizAttempt;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SubmitQuizAttemptAction
{
    /**
     * $answers: [question_id => option_id, ...]. Every question in the quiz
     * must be answered — missing or extra question ids are rejected before
     * any row is written. Correctness is determined purely from
     * quiz_options.is_correct, looked up fresh here — the client never
     * tells us what's correct, only which option it picked.
     */
    public function execute(User $student, Quiz $quiz, array $answers): QuizAttempt
    {
        $quiz->loadMissing('questions.options');

        $questionIds = $quiz->questions->pluck('id');

        if ($questionIds->sort()->values()->all() !== collect(array_keys($answers))->map(fn ($id) => (int) $id)->sort()->values()->all()) {
            throw ValidationException::withMessages([
                'answers' => ['Every question in this quiz must be answered exactly once.'],
            ]);
        }

        return DB::transaction(function () use ($student, $quiz, $answers) {
            $correctCount = 0;
            $rows = [];

            foreach ($quiz->questions as $question) {
                $selectedOptionId = (int) $answers[$question->id];
                $selectedOption = $question->options->firstWhere('id', $selectedOptionId);

                if (! $selectedOption) {
                    throw ValidationException::withMessages([
                        'answers' => ["The selected option for question {$question->id} does not belong to this quiz."],
                    ]);
                }

                $isCorrect = (bool) $selectedOption->is_correct;
                if ($isCorrect) {
                    $correctCount++;
                }

                $rows[] = [
                    'quiz_question_id' => $question->id,
                    'quiz_option_id' => $selectedOptionId,
                    'is_correct' => $isCorrect,
                ];
            }

            $total = $quiz->questions->count();
            $scorePercent = $total > 0 ? (int) round($correctCount / $total * 100) : 0;

            $attempt = QuizAttempt::create([
                'quiz_id' => $quiz->id,
                'student_id' => $student->id,
                'score_percent' => $scorePercent,
                'correct_count' => $correctCount,
                'total_questions' => $total,
                'submitted_at' => now(),
            ]);

            foreach ($rows as $row) {
                QuizAnswer::create([...$row, 'quiz_attempt_id' => $attempt->id]);
            }

            return $attempt->load('answers');
        });
    }
}
