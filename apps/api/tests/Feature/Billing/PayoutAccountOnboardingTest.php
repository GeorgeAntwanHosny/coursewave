<?php

namespace Tests\Feature\Billing;

use App\Actions\Billing\CreateOrResumePayoutOnboardingAction;
use App\Actions\Billing\RefreshPayoutAccountStatusAction;
use App\Models\InstructorPayoutAccount;
use App\Models\User;
use App\Services\Payments\PaymentGatewayInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\Fakes\FakePaymentGateway;
use Tests\TestCase;

class PayoutAccountOnboardingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Role::firstOrCreate(['name' => 'instructor', 'guard_name' => 'web']);
        $this->app->bind(PaymentGatewayInterface::class, FakePaymentGateway::class);
        FakePaymentGateway::reset();
    }

    protected function instructor(): User
    {
        $user = User::factory()->create();
        $user->assignRole('instructor');

        return $user;
    }

    public function test_new_accounts_are_created_as_v2_and_the_version_is_stored(): void
    {
        $instructor = $this->instructor();

        $url = app(CreateOrResumePayoutOnboardingAction::class)->execute($instructor, 'https://x/return', 'https://x/refresh');

        $account = InstructorPayoutAccount::where('instructor_id', $instructor->id)->firstOrFail();
        $this->assertEquals('v2', $account->provider_account_version);
        $this->assertFalse($account->payouts_enabled);
        $this->assertStringContainsString($account->provider_account_id, $url);
        $this->assertEquals('v2', FakePaymentGateway::$refsSeen[0]->version);
    }

    public function test_resuming_onboarding_reuses_the_existing_account(): void
    {
        $instructor = $this->instructor();
        $action = app(CreateOrResumePayoutOnboardingAction::class);

        $action->execute($instructor, 'https://x/return', 'https://x/refresh');
        $action->execute($instructor, 'https://x/return', 'https://x/refresh');

        $this->assertEquals(1, InstructorPayoutAccount::where('instructor_id', $instructor->id)->count());
    }

    public function test_legacy_v1_accounts_keep_using_the_v1_path(): void
    {
        $instructor = $this->instructor();
        $legacy = InstructorPayoutAccount::create([
            'instructor_id' => $instructor->id,
            'provider' => 'stripe',
            'provider_account_id' => 'acct_legacy',
            // provider_account_version omitted: the DB default is 'v1'
        ]);

        $this->assertEquals('v1', $legacy->fresh()->provider_account_version);

        app(CreateOrResumePayoutOnboardingAction::class)->execute($instructor, 'https://x/return', 'https://x/refresh');

        $this->assertEquals('acct_legacy', FakePaymentGateway::$refsSeen[0]->id);
        $this->assertEquals('v1', FakePaymentGateway::$refsSeen[0]->version);
    }

    public function test_refresh_updates_payouts_enabled_from_the_gateway(): void
    {
        $instructor = $this->instructor();
        $account = InstructorPayoutAccount::create([
            'instructor_id' => $instructor->id,
            'provider' => 'stripe',
            'provider_account_id' => 'acct_x',
            'provider_account_version' => 'v2',
            'payouts_enabled' => false,
        ]);

        FakePaymentGateway::$accountEnabled = true;
        $this->assertTrue(app(RefreshPayoutAccountStatusAction::class)->execute($account)->payouts_enabled);

        FakePaymentGateway::$accountEnabled = false;
        $this->assertFalse(app(RefreshPayoutAccountStatusAction::class)->execute($account->fresh())->payouts_enabled);
    }
}
