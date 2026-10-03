<?php

namespace Tests\Feature\Billing;

use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use App\Models\WebhookEvent;
use App\Services\Payments\PaymentGatewayInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Stripe\WebhookSignature;
use Tests\Fakes\FakePaymentGateway;
use Tests\TestCase;

class StripeWebhookTest extends TestCase
{
    use RefreshDatabase;

    protected string $webhookSecret = 'whsec_test_secret';

    protected function setUp(): void
    {
        parent::setUp();
        Role::firstOrCreate(['name' => 'student', 'guard_name' => 'web']);
        $this->app->bind(PaymentGatewayInterface::class, FakePaymentGateway::class);
        FakePaymentGateway::reset();
        config(['services.stripe.webhook_secret' => $this->webhookSecret]);
    }

    protected function postWebhook(array $payload, ?string $signatureOverride = null)
    {
        $body = json_encode($payload);
        $header = $signatureOverride ?? WebhookSignature::generateSignatureHeader($body, $this->webhookSecret);

        return $this->call('POST', '/api/webhooks/stripe', [], [], [], [
            'HTTP_Stripe-Signature' => $header,
            'CONTENT_TYPE' => 'application/json',
        ], $body);
    }

    public function test_webhook_confirms_subscription_on_checkout_completed(): void
    {
        $student = User::factory()->create();
        $student->assignRole('student');
        $plan = Plan::factory()->create(['price_cents' => 1999, 'term_days' => 30]);
        $token = $student->createToken('test')->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/subscriptions/checkout', ['plan_id' => $plan->id]);

        $subscription = Subscription::where('student_id', $student->id)->first();
        $sessionId = $subscription->provider_checkout_id;

        $response = $this->postWebhook([
            'id' => 'evt_test_123',
            'type' => 'checkout.session.completed',
            'data' => ['object' => ['id' => $sessionId]],
        ]);

        $response->assertStatus(200);

        $subscription->refresh();
        $this->assertEquals('active', $subscription->status);
        $this->assertEquals(30, $subscription->recognitionSlices()->count());
        $this->assertDatabaseHas('webhook_events', [
            'provider' => 'stripe',
            'provider_event_id' => 'evt_test_123',
        ]);
    }

    public function test_webhook_is_idempotent_for_duplicate_event_delivery(): void
    {
        $student = User::factory()->create();
        $student->assignRole('student');
        $plan = Plan::factory()->create(['price_cents' => 1999, 'term_days' => 30]);
        $token = $student->createToken('test')->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/subscriptions/checkout', ['plan_id' => $plan->id]);

        $sessionId = Subscription::where('student_id', $student->id)->first()->provider_checkout_id;

        $payload = [
            'id' => 'evt_test_dup',
            'type' => 'checkout.session.completed',
            'data' => ['object' => ['id' => $sessionId]],
        ];

        $this->postWebhook($payload)->assertStatus(200);
        $this->postWebhook($payload)->assertStatus(200);

        $this->assertEquals(1, WebhookEvent::where('provider_event_id', 'evt_test_dup')->count());
        $this->assertEquals(1, Subscription::where('student_id', $student->id)->count());
    }

    public function test_frontend_confirm_and_webhook_converge_safely_regardless_of_order(): void
    {
        $student = User::factory()->create();
        $student->assignRole('student');
        $plan = Plan::factory()->create(['price_cents' => 1999, 'term_days' => 30]);
        $token = $student->createToken('test')->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/subscriptions/checkout', ['plan_id' => $plan->id]);

        $sessionId = Subscription::where('student_id', $student->id)->first()->provider_checkout_id;

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/subscriptions/confirm', ['session_id' => $sessionId])
            ->assertStatus(200);

        $this->postWebhook([
            'id' => 'evt_test_convergence',
            'type' => 'checkout.session.completed',
            'data' => ['object' => ['id' => $sessionId]],
        ])->assertStatus(200);

        $this->assertEquals(1, Subscription::where('student_id', $student->id)->count());
        $subscription = Subscription::where('student_id', $student->id)->first();
        $this->assertEquals(30, $subscription->recognitionSlices()->count());
    }

    public function test_webhook_rejects_invalid_signature(): void
    {
        $response = $this->postWebhook(
            ['id' => 'evt_fake', 'type' => 'checkout.session.completed'],
            signatureOverride: 't=1,v1=invalid'
        );

        $response->assertStatus(400);
    }
}
