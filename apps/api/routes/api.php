<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CertificateController;
use App\Http\Controllers\Api\CommentController;
use App\Http\Controllers\Api\CourseController;
use App\Http\Controllers\Api\DevCertificateToolsController;
use App\Http\Controllers\Api\LearningController;
use App\Http\Controllers\Api\LiveSessionController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\PlanController;
use App\Http\Controllers\Api\QuizController;
use App\Http\Controllers\Api\StripeWebhookController;
use App\Http\Controllers\Api\SubscriptionController;
use Illuminate\Broadcasting\BroadcastController;
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

// Public certificate verification (throttled; returns only what is printed on the certificate)
Route::get('/certificates/verify/{number}', [CertificateController::class, 'verify'])->middleware('throttle:30,1');

// Stripe webhook — public, signature-verified internally, not session/token authenticated
Route::post('/webhooks/stripe', [StripeWebhookController::class, 'handle']);

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me', [AuthController::class, 'me']);

    // Private-channel authorization for Laravel Echo over a Sanctum bearer token
    // (the Next.js app has no session). Channel rules live in routes/channels.php.
    Route::post('/broadcasting/auth', [BroadcastController::class, 'authenticate']);

    // Notifications: any signed-in user, no subscription needed (instructors and admins get them too).
    Route::get('/notifications', [NotificationController::class, 'index']);
    Route::get('/notifications/unread-count', [NotificationController::class, 'unreadCount']);
    Route::post('/notifications/read-all', [NotificationController::class, 'markAllRead']);
    Route::post('/notifications/{id}/read', [NotificationController::class, 'markRead'])->whereUuid('id');

    Route::post('/subscriptions/checkout', [SubscriptionController::class, 'checkout']);
    Route::post('/subscriptions/confirm', [SubscriptionController::class, 'confirm']);
    Route::get('/subscriptions', [SubscriptionController::class, 'index']);
    Route::get('/subscriptions/current', [SubscriptionController::class, 'current']);
    Route::post('/subscriptions/current/cancel', [SubscriptionController::class, 'cancelCurrent']);

    // A student keeps their certificates even after a subscription ends.
    Route::get('/certificates', [CertificateController::class, 'index']);
    Route::get('/certificates/{id}/download', [CertificateController::class, 'download'])->whereNumber('id');

    Route::middleware('subscription.active')->prefix('learning')->group(function () {
        Route::get('/courses/{slug}', [LearningController::class, 'courseTree']);
        Route::get('/lessons/{lesson}', [LearningController::class, 'lesson']);
        Route::post('/lessons/{lesson}/progress', [LearningController::class, 'recordProgress']);

        Route::get('/quizzes/{quiz}', [QuizController::class, 'show']);
        Route::post('/quizzes/{quiz}/submit', [QuizController::class, 'submit']);
        Route::get('/quizzes/{quiz}/attempts', [QuizController::class, 'attempts']);

        // Live sessions: subscribers only (the join link is part of what a subscription unlocks).
        Route::get('/live-sessions', [LiveSessionController::class, 'index']);
        Route::get('/courses/{slug}/live-sessions', [LiveSessionController::class, 'forCourse']);
    });

    // Comments are authorized by CommentPolicy (active subscription, course instructor or admin),
    // not by the subscription middleware, so a course's instructor can reply without subscribing.
    Route::prefix('learning')->group(function () {
        Route::get('/lessons/{lesson}/comments', [CommentController::class, 'index']);
        Route::post('/lessons/{lesson}/comments', [CommentController::class, 'store'])->middleware('throttle:20,1');
        Route::patch('/comments/{comment}', [CommentController::class, 'update'])->middleware('throttle:20,1');
        Route::delete('/comments/{comment}', [CommentController::class, 'destroy']);
    });

    // LOCAL/TESTING ONLY — registered only in those environments, and the controller
    // re-checks the environment, so a cached route file can't expose it in production.
    if (app()->environment(['local', 'testing'])) {
        Route::post('/dev/complete-courses', [DevCertificateToolsController::class, 'completeCourses']);
        Route::delete('/dev/reset-learning', [DevCertificateToolsController::class, 'reset']);
    }
});
