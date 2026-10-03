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

class SubscriptionSwitchingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Role::firstOrCreate(['name' => 'student', 'guard_name' => 'web']);
        $this->app->bind(PaymentGatewayInterface::class, FakePaymentGateway::class);
        FakePaymentGateway::reset();
    }

    protected function subscribeAndConfirm(User $student, string $token, Plan $plan): Subscription
    {
        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/subscriptions/checkout', ['plan_id' => $plan->id]);

        $sessionId = Subscription::where('student_id', $student->id)
            ->where('plan_id', $plan->id)
            ->latest('id')
            ->first()
            ->provider_checkout_id;

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/subscriptions/confirm', ['session_id' => $sessionId]);

        return Subscription::where('provider_checkout_id', $sessionId)->first();
    }

    public function test_student_cannot_resubscribe_to_already_active_plan(): void
    {
        $student = User::factory()->create();
        $student->assignRole('student');
        $plan = Plan::factory()->create(['price_cents' => 1999, 'term_days' => 30]);
        $token = $student->createToken('test')->plainTextToken;

        $this->subscribeAndConfirm($student, $token, $plan);

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/subscriptions/checkout', ['plan_id' => $plan->id]);

        $response->assertStatus(422)->assertJsonPath('success', false);
    }

    public function test_confirming_a_different_plan_cancels_the_previous_active_subscription(): void
    {
        $student = User::factory()->create();
        $student->assignRole('student');
        $planA = Plan::factory()->create(['price_cents' => 1999, 'term_days' => 30, 'slug' => 'plan-a']);
        $planB = Plan::factory()->create(['price_cents' => 4999, 'term_days' => 90, 'slug' => 'plan-b']);
        $token = $student->createToken('test')->plainTextToken;

        $subA = $this->subscribeAndConfirm($student, $token, $planA);
        $this->assertEquals('active', $subA->status);
        $this->assertEquals(30, $subA->recognitionSlices()->where('status', 'pending')->count());

        $subB = $this->subscribeAndConfirm($student, $token, $planB);

        $subA->refresh();
        $subB->refresh();

        $this->assertEquals('canceled', $subA->status);
        $this->assertNotNull($subA->canceled_at);
        $this->assertEquals('active', $subB->status);

        // All of plan A's slices were still pending (no time had elapsed in
        // the test), so all should now be voided, none left pending.
        $this->assertEquals(0, $subA->recognitionSlices()->where('status', 'pending')->count());
        $this->assertEquals(30, $subA->recognitionSlices()->where('status', 'voided')->count());

        $this->assertEquals(90, $subB->recognitionSlices()->where('status', 'pending')->count());
    }

    public function test_only_one_active_subscription_exists_per_student_at_db_level(): void
    {
        $student = User::factory()->create();
        $student->assignRole('student');
        $planA = Plan::factory()->create(['term_days' => 30, 'slug' => 'a']);
        $planB = Plan::factory()->create(['term_days' => 90, 'slug' => 'b']);
        $token = $student->createToken('test')->plainTextToken;

        $this->subscribeAndConfirm($student, $token, $planA);
        $this->subscribeAndConfirm($student, $token, $planB);

        $this->assertEquals(1, Subscription::where('student_id', $student->id)->where('status', 'active')->count());
    }

    public function test_current_endpoint_returns_active_subscription(): void
    {
        $student = User::factory()->create();
        $student->assignRole('student');
        $plan = Plan::factory()->create(['term_days' => 30]);
        $token = $student->createToken('test')->plainTextToken;

        $this->subscribeAndConfirm($student, $token, $plan);

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/subscriptions/current');

        $response->assertStatus(200)->assertJsonPath('data.status', 'active');
    }

    public function test_current_endpoint_returns_null_when_no_active_subscription(): void
    {
        $student = User::factory()->create();
        $student->assignRole('student');
        $token = $student->createToken('test')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/subscriptions/current');

        $response->assertStatus(200)->assertJsonPath('data', null);
    }
}
