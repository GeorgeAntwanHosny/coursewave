<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CourseController;
use App\Http\Controllers\Api\LearningController;
use App\Http\Controllers\Api\PlanController;
use App\Http\Controllers\Api\QuizController;
use App\Http\Controllers\Api\StripeWebhookController;
use App\Http\Controllers\Api\SubscriptionController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// Simple connection-test endpoint for the Next.js frontend.
// Visit http://localhost:8000/api/ping (or fetch it from web/) to verify
// the Laravel API and the Next.js app can talk to each other.
Route::get('/ping', function (Request $request) {
    return response()->json([
        'message' => 'pong from LearnLedger API 👋',
        'app' => config('app.name'),
        'time' => now()->toIso8601String(),
    ]);
});

// Auth
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

// Public catalog
Route::get('/courses', [CourseController::class, 'index']);
Route::get('/courses/{slug}', [CourseController::class, 'show']);

// Public plans
Route::get('/plans', [PlanController::class, 'index']);

// Stripe webhook — public, signature-verified internally, not session/token authenticated
Route::post('/webhooks/stripe', [StripeWebhookController::class, 'handle']);

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me', [AuthController::class, 'me']);

    Route::post('/subscriptions/checkout', [SubscriptionController::class, 'checkout']);
    Route::post('/subscriptions/confirm', [SubscriptionController::class, 'confirm']);
    Route::get('/subscriptions', [SubscriptionController::class, 'index']);
    Route::get('/subscriptions/current', [SubscriptionController::class, 'current']);

    Route::middleware('subscription.active')->prefix('learning')->group(function () {
        Route::get('/courses/{slug}', [LearningController::class, 'courseTree']);
        Route::get('/lessons/{lesson}', [LearningController::class, 'lesson']);
        Route::post('/lessons/{lesson}/progress', [LearningController::class, 'recordProgress']);

        Route::get('/quizzes/{quiz}', [QuizController::class, 'show']);
        Route::post('/quizzes/{quiz}/submit', [QuizController::class, 'submit']);
        Route::get('/quizzes/{quiz}/attempts', [QuizController::class, 'attempts']);
    });
});
