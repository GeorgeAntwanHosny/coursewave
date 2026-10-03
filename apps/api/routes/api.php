<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CourseController;
use App\Http\Controllers\Api\PlanController;
use App\Http\Controllers\Api\StripeWebhookController;
use App\Http\Controllers\Api\SubscriptionController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| These routes are loaded by the RouteServiceProvider and all of them
| will be assigned to the "api" middleware group.
|
*/

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


Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me', [AuthController::class, 'me']);
});

Route::get('/courses', [CourseController::class, 'index']);
Route::get('/courses/{slug}', [CourseController::class, 'show']);

Route::get('/plans', [PlanController::class, 'index']);

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/subscriptions/checkout', [SubscriptionController::class, 'checkout']);
    Route::post('/subscriptions/confirm', [SubscriptionController::class, 'confirm']);
    Route::get('/subscriptions', [SubscriptionController::class, 'index']);
    Route::get('/subscriptions/current', [SubscriptionController::class, 'current']);
});

Route::post('/webhooks/stripe', [StripeWebhookController::class, 'handle']);
