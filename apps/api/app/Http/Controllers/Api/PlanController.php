<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\PlanResource;
use App\Models\Plan;
use App\Support\ApiResponse;

class PlanController extends Controller
{
    public function index()
    {
        $plans = Plan::where('is_active', true)->orderBy('price_cents')->get();

        return ApiResponse::success(PlanResource::collection($plans), 'Plans retrieved.');
    }
}
