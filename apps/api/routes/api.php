<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

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
