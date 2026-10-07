<?php

namespace Tests\Feature\Learning;

use App\Models\Course;
use App\Models\Lesson;
use App\Models\Module;
use App\Models\Plan;
use App\Models\Quiz;
use App\Models\QuizOption;
use App\Models\QuizQuestion;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class QuizAttemptTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Role::firstOrCreate(['name' => 'student', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'instructor', 'guard_name' => 'web']);
    }

    protected function buildQuiz(): array
    {
        $instructor = User::factory()->create();
        $instructor->assignRole('instructor');
        $course = Course::factory()->create(['instructor_id' => $instructor->id, 'status' => 'published']);
        $module = Module::factory()->create(['course_id' => $course->id]);
        $lesson = Lesson::factory()->create(['module_id' => $module->id, 'type' => 'text', 'body' => 'short']);
        $quiz = Quiz::factory()->create(['lesson_id' => $lesson->id]);

        $q1 = QuizQuestion::create(['quiz_id' => $quiz->id, 'question' => 'Q1?', 'order' => 1]);
        $q1CorrectOption = QuizOption::create(['quiz_question_id' => $q1->id, 'option_text' => 'Right', 'is_correct' => true]);
        $q1WrongOption = QuizOption::create(['quiz_question_id' => $q1->id, 'option_text' => 'Wrong', 'is_correct' => false]);

        $q2 = QuizQuestion::create(['quiz_id' => $quiz->id, 'question' => 'Q2?', 'order' => 2]);
        $q2CorrectOption = QuizOption::create(['quiz_question_id' => $q2->id, 'option_text' => 'Right', 'is_correct' => true]);
        $q2WrongOption = QuizOption::create(['quiz_question_id' => $q2->id, 'option_text' => 'Wrong', 'is_correct' => false]);

        return compact('quiz', 'q1', 'q1CorrectOption', 'q1WrongOption', 'q2', 'q2CorrectOption', 'q2WrongOption');
    }

    protected function studentToken(): array
    {
        $student = User::factory()->create();
        $student->assignRole('student');
        $plan = Plan::factory()->create();
        Subscription::factory()->create(['student_id' => $student->id, 'plan_id' => $plan->id, 'status' => 'active']);
        $token = $student->createToken('test')->plainTextToken;

        return [$student, $token];
    }

    public function test_quiz_detail_does_not_leak_correct_answers(): void
    {
        ['quiz' => $quiz] = $this->buildQuiz();
        [, $token] = $this->studentToken();

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson("/api/learning/quizzes/{$quiz->id}");

        $response->assertStatus(200);
        $this->assertArrayNotHasKey('is_correct', $response->json('data.questions.0.options.0'));
    }

    public function test_submitting_all_correct_answers_scores_100_percent(): void
    {
        ['quiz' => $quiz, 'q1' => $q1, 'q1CorrectOption' => $o1, 'q2' => $q2, 'q2CorrectOption' => $o2] = $this->buildQuiz();
        [, $token] = $this->studentToken();

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/learning/quizzes/{$quiz->id}/submit", [
                'answers' => [$q1->id => $o1->id, $q2->id => $o2->id],
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.score_percent', 100)
            ->assertJsonPath('data.correct_count', 2)
            ->assertJsonPath('data.total_questions', 2);
    }

    public function test_submitting_mixed_answers_scores_partial_credit(): void
    {
        ['quiz' => $quiz, 'q1' => $q1, 'q1WrongOption' => $wrong1, 'q2' => $q2, 'q2CorrectOption' => $correct2] = $this->buildQuiz();
        [, $token] = $this->studentToken();

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/learning/quizzes/{$quiz->id}/submit", [
                'answers' => [$q1->id => $wrong1->id, $q2->id => $correct2->id],
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.score_percent', 50)
            ->assertJsonPath('data.correct_count', 1);
    }

    public function test_cannot_submit_with_a_missing_question(): void
    {
        ['quiz' => $quiz, 'q1' => $q1, 'q1CorrectOption' => $o1] = $this->buildQuiz();
        [, $token] = $this->studentToken();

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/learning/quizzes/{$quiz->id}/submit", [
                'answers' => [$q1->id => $o1->id], // q2 missing
            ]);

        $response->assertStatus(422)->assertJsonPath('success', false);
    }

    public function test_cannot_submit_an_option_belonging_to_a_different_question(): void
    {
        ['quiz' => $quiz, 'q1' => $q1, 'q2' => $q2, 'q2CorrectOption' => $o2, 'q1CorrectOption' => $o1] = $this->buildQuiz();
        [, $token] = $this->studentToken();

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/learning/quizzes/{$quiz->id}/submit", [
                'answers' => [$q1->id => $o2->id, $q2->id => $o1->id], // swapped — options don't belong to those questions
            ]);

        $response->assertStatus(422)->assertJsonPath('success', false);
    }

    public function test_multiple_attempts_are_recorded_and_best_attempt_returned(): void
    {
        ['quiz' => $quiz, 'q1' => $q1, 'q1WrongOption' => $wrong1, 'q1CorrectOption' => $correct1, 'q2' => $q2, 'q2CorrectOption' => $correct2] = $this->buildQuiz();
        [, $token] = $this->studentToken();

        // First attempt: 50%
        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/learning/quizzes/{$quiz->id}/submit", [
                'answers' => [$q1->id => $wrong1->id, $q2->id => $correct2->id],
            ]);

        // Second attempt: 100%
        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/learning/quizzes/{$quiz->id}/submit", [
                'answers' => [$q1->id => $correct1->id, $q2->id => $correct2->id],
            ]);

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson("/api/learning/quizzes/{$quiz->id}");

        $response->assertStatus(200)
            ->assertJsonPath('data.attempt_count', 2)
            ->assertJsonPath('data.best_attempt.score_percent', 100);

        $attemptsResponse = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson("/api/learning/quizzes/{$quiz->id}/attempts");

        $attemptsResponse->assertStatus(200)->assertJsonCount(2, 'data');
    }

    public function test_student_without_active_subscription_cannot_take_quiz(): void
    {
        ['quiz' => $quiz] = $this->buildQuiz();
        $student = User::factory()->create();
        $student->assignRole('student');
        $token = $student->createToken('test')->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson("/api/learning/quizzes/{$quiz->id}")
            ->assertStatus(403);
    }
}
