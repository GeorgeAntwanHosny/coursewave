<?php

namespace Tests\Feature\Notifications;

use App\Models\User;
use App\Notifications\AdminBillingNotification;
use App\Notifications\InstructorNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class FilamentNotificationsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        foreach (['instructor', 'admin'] as $role) {
            Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);
        }
    }

    public function test_the_payload_carries_filaments_format_a_view_action_and_our_flat_fields(): void
    {
        $user = User::factory()->create();

        $instructorData = InstructorNotification::payoutAccountReady()->toArray($user);
        $adminData = AdminBillingNotification::payoutRun(2, 5000)->toArray($user);

        foreach ([$instructorData, $adminData] as $data) {
            $this->assertEquals('filament', $data['format']);
            $this->assertNotEmpty($data['actions']);   // the "View" link
            $this->assertNotEmpty($data['title']);
            $this->assertNotEmpty($data['kind']);
            $this->assertStringStartsWith('http', $data['url']); // Filament pages are absolute URLs
        }

        $this->assertEquals('payout_account_ready', $instructorData['kind']);
        $this->assertEquals('admin_payout_run', $adminData['kind']);
        $this->assertStringContainsString('/instructor/', $instructorData['url']);
        $this->assertStringContainsString('/admin/', $adminData['url']);
    }

    public function test_a_stored_notification_is_listed_by_filaments_own_query_and_marked_read_in_one_place(): void
    {
        $instructor = User::factory()->create();
        $instructor->assignRole('instructor');

        // Sent immediately (not queued) so the stored row can be inspected; broadcasting is null in tests.
        $instructor->notifyNow(InstructorNotification::payoutAccountReady());

        // The exact condition Filament's bell uses. It only works on a JSON column, so this also
        // proves the migration ran.
        $this->assertEquals(1, $instructor->notifications()->where('data->format', 'filament')->count());
        $this->assertEquals(1, $instructor->unreadNotifications()->where('data->format', 'filament')->count());

        $instructor->unreadNotifications()->update(['read_at' => now()]);

        $this->assertEquals(0, $instructor->unreadNotifications()->count()); // read state is shared with the Next.js bell
    }
}
