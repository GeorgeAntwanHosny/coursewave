<?php

namespace App\Http\Controllers\Api;

use App\Actions\Learning\SubmitQuizAttemptAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Learning\SubmitQuizRequest;
use App\Http\Resources\QuizAttemptResultResource;
use App\Http\Resources\QuizTakeResource;
use App\Models\Quiz;
use App\Support\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class QuizController extends Controller
{
    public function show(Request $request, Quiz $quiz)
    {
        $quiz->loadMissing(['lesson.module.course', 'questions.options']);

        if ($quiz->lesson->module->course->status !== 'published') {
            return ApiResponse::error('Quiz not found.', null, 404);
        }

        $student = $request->user();

        $attempts = $quiz->attempts()->where('student_id', $student->id)->get();
        $quiz->setAttribute('bestAttempt', $attempts->sortByDesc('score_percent')->first());
        $quiz->setAttribute('attemptCount', $attempts->count());

        return ApiResponse::success(new QuizTakeResource($quiz), 'Quiz retrieved.');
    }

    public function submit(SubmitQuizRequest $request, Quiz $quiz, SubmitQuizAttemptAction $action)
    {
        $quiz->loadMissing('lesson.module.course');

        if ($quiz->lesson->module->course->status !== 'published') {
            return ApiResponse::error('Quiz not found.', null, 404);
        }

        try {
            $attempt = $action->execute($request->user(), $quiz, $request->validated('answers'));
        } catch (ValidationException $e) {
            return ApiResponse::error($e->getMessage(), $e->errors(), 422);
        }

        return ApiResponse::success(new QuizAttemptResultResource($attempt), 'Quiz submitted.');
    }

    public function attempts(Request $request, Quiz $quiz)
    {
        $attempts = $quiz->attempts()
            ->where('student_id', $request->user()->id)
            ->latest('submitted_at')
            ->get();

        return ApiResponse::success(QuizAttemptResultResource::collection($attempts), 'Attempts retrieved.');
    }
}
