<?php

namespace App\Http\Controllers\Api;

use App\Actions\Billing\CancelSubscriptionAction;
use App\Actions\Billing\ConfirmSubscriptionAction;
use App\Actions\Billing\InitiateCheckoutAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Billing\CheckoutRequest;
use App\Http\Requests\Billing\ConfirmSubscriptionRequest;
use App\Http\Resources\RefundResource;
use App\Http\Resources\SubscriptionResource;
use App\Models\Plan;
use App\Models\Subscription;
use App\Support\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class SubscriptionController extends Controller
{
    public function checkout(CheckoutRequest $request, InitiateCheckoutAction $action)
    {
        $plan = Plan::findOrFail($request->validated('plan_id'));

        $activeSubscription = Subscription::where('student_id', $request->user()->id)
            ->where('status', 'active')
            ->first();

        if ($activeSubscription && $activeSubscription->plan_id === $plan->id) {
            return ApiResponse::error(
                'You are already subscribed to this plan.',
                ['plan_id' => ['You already have an active subscription to this plan.']],
                422
            );
        }

        $session = $action->execute($request->user(), $plan);

        return ApiResponse::success(['checkout_url' => $session->url], 'Checkout session created.');
    }

    public function confirm(ConfirmSubscriptionRequest $request, ConfirmSubscriptionAction $action)
    {
        try {
            $subscription = $action->execute($request->validated('session_id'), $request->user()->id);
        } catch (ValidationException $e) {
            return ApiResponse::error($e->getMessage(), $e->errors(), 422);
        }

        return ApiResponse::success(
            new SubscriptionResource($subscription->load('plan')),
            'Subscription confirmed.'
        );
    }

    public function index(Request $request)
    {
        $subscriptions = $request->user()->subscriptions()->with('plan')->latest()->get();

        return ApiResponse::success(SubscriptionResource::collection($subscriptions), 'Subscriptions retrieved.');
    }

    public function current(Request $request)
    {
        $subscription = $request->user()->subscriptions()
            ->where('status', 'active')
            ->with('plan')
            ->first();

        if ($subscription) {
            $subscription->setAttribute('refundable_cents', CancelSubscriptionAction::refundableCents($subscription));
        }

        return ApiResponse::success(
            $subscription ? new SubscriptionResource($subscription) : null,
            $subscription ? 'Active subscription found.' : 'No active subscription.'
        );
    }

    public function cancelCurrent(Request $request, CancelSubscriptionAction $action)
    {
        $subscription = $request->user()->subscriptions()->where('status', 'active')->first();

        if (! $subscription) {
            return ApiResponse::error('You have no active subscription to cancel.', null, 422);
        }

        $refund = $action->execute($subscription, 'student_cancel', $request->user()->id);

        return ApiResponse::success([
            'subscription' => new SubscriptionResource($subscription->fresh()->load('plan')),
            'refund' => $refund ? new RefundResource($refund) : null,
        ], 'Subscription canceled.');
    }
}
