<?php

namespace App\Http\Controllers\Api;

use App\Actions\Billing\ConfirmSubscriptionAction;
use App\Http\Controllers\Controller;
use App\Models\WebhookEvent;
use App\Services\Payments\PaymentGatewayInterface;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Stripe\Event;
use Stripe\Exception\SignatureVerificationException;
use Stripe\Webhook;

class StripeWebhookController extends Controller
{
    public function __construct(protected PaymentGatewayInterface $gateway) {}

    public function handle(Request $request, ConfirmSubscriptionAction $action)
    {
        $payload = $request->getContent();
        $sigHeader = $request->header('Stripe-Signature');
        $secret = config('services.stripe.webhook_secret');

        try {
            $event = Webhook::constructEvent($payload, $sigHeader, $secret);
        } catch (\UnexpectedValueException|SignatureVerificationException $e) {
            Log::warning('Stripe webhook signature verification failed', ['error' => $e->getMessage()]);
            return response()->json(['error' => 'Invalid signature.'], 400);
        }

        $provider = $this->gateway->getProviderName();

        if (WebhookEvent::where('provider', $provider)->where('provider_event_id', $event->id)->exists()) {
            return response()->json(['status' => 'duplicate, already processed'], 200);
        }

        try {
            $this->process($event, $action);
        } catch (ValidationException $e) {
            Log::warning('Stripe webhook business validation failed', [
                'event_id' => $event->id,
                'error' => $e->getMessage(),
            ]);
            return response()->json(['status' => 'acknowledged, not processed'], 200);
        } catch (\Throwable $e) {
            Log::error('Stripe webhook processing failed', [
                'event_id' => $event->id,
                'error' => $e->getMessage(),
            ]);
            return response()->json(['error' => 'Processing failed.'], 500);
        }

        WebhookEvent::create([
            'provider' => $provider,
            'provider_event_id' => $event->id,
            'type' => $event->type,
            'processed_at' => now(),
        ]);

        return response()->json(['status' => 'processed'], 200);
    }

    protected function process(Event $event, ConfirmSubscriptionAction $action): void
    {
        match ($event->type) {
            'checkout.session.completed', 'checkout.session.async_payment_succeeded' =>
                $action->execute($event->data->object->id),
            default => Log::info('Stripe webhook event ignored (no handler)', ['type' => $event->type]),
        };
    }
}
