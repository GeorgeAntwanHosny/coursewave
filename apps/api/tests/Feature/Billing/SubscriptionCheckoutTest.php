<?php

namespace Tests\Feature\Billing;

use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use App\Services\Payments\PaymentGatewayInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\Fakes\FakePaymentGateway;
use Tests\TestCase;

class SubscriptionCheckoutTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Role::firstOrCreate(['name' => 'student', 'guard_name' => 'web']);
        $this->app->bind(PaymentGatewayInterface::class, FakePaymentGateway::class);
        FakePaymentGateway::reset();
    }

    public function test_authenticated_student_can_initiate_checkout(): void
    {
        $student = User::factory()->create();
        $student->assignRole('student');
        $plan = Plan::factory()->create(['price_cents' => 1999, 'term_days' => 30]);
        $token = $student->createToken('test')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/subscriptions/checkout', ['plan_id' => $plan->id]);

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonStructure(['data' => ['checkout_url']]);

        $this->assertDatabaseHas('subscriptions', [
            'student_id' => $student->id,
            'plan_id' => $plan->id,
            'status' => 'pending',
            'provider' => 'stripe',
        ]);
    }

    public function test_guest_cannot_initiate_checkout(): void
    {
        $plan = Plan::factory()->create();

        $this->postJson('/api/subscriptions/checkout', ['plan_id' => $plan->id])
            ->assertStatus(401);
    }

    public function test_confirm_creates_subscription_with_correct_recognition_slices(): void
    {
        $student = User::factory()->create();
        $student->assignRole('student');
        $plan = Plan::factory()->create(['price_cents' => 1000, 'term_days' => 3]);
        $token = $student->createToken('test')->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/subscriptions/checkout', ['plan_id' => $plan->id]);

        $sessionId = Subscription::where('student_id', $student->id)->first()->provider_checkout_id;

        $confirm = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/subscriptions/confirm', ['session_id' => $sessionId]);

        $confirm->assertStatus(200)->assertJsonPath('data.status', 'active');

        $subscription = Subscription::where('student_id', $student->id)->first();
        $this->assertEquals(3, $subscription->recognitionSlices()->count());
        $this->assertEquals(1000, $subscription->recognitionSlices()->sum('amount_cents'));

        $lastSlice = $subscription->recognitionSlices()->orderBy('slice_date', 'desc')->first();
        $this->assertEquals(334, $lastSlice->amount_cents);
    }

    public function test_confirm_is_idempotent_for_same_session(): void
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

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/subscriptions/confirm', ['session_id' => $sessionId])
            ->assertStatus(200);

        $this->assertEquals(1, Subscription::where('student_id', $student->id)->count());
        $subscription = Subscription::where('student_id', $student->id)->first();
        $this->assertEquals(30, $subscription->recognitionSlices()->count());
    }

    public function test_confirm_fails_if_payment_not_completed(): void
    {
        FakePaymentGateway::$paymentStatus = 'unpaid';

        $student = User::factory()->create();
        $student->assignRole('student');
        $plan = Plan::factory()->create();
        $token = $student->createToken('test')->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/subscriptions/checkout', ['plan_id' => $plan->id]);

        $sessionId = Subscription::where('student_id', $student->id)->first()->provider_checkout_id;

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/subscriptions/confirm', ['session_id' => $sessionId])
            ->assertStatus(422)
            ->assertJsonPath('success', false);
    }
}
