<?php

namespace Tests\Feature\Analytics;

use App\Filament\Admin\Widgets\PlatformAttention;
use App\Filament\Admin\Widgets\PlatformWeekStats;
use App\Filament\Instructor\Widgets\InstructorAttention;
use App\Filament\Instructor\Widgets\InstructorWeekStats;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Concerns\BuildsLiveSessionFixtures;
use Tests\TestCase;

/** Smoke tests: the dashboard widgets render for the right user and say something useful. */
class DashboardWidgetsTest extends TestCase
{
    use BuildsLiveSessionFixtures, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
        config(['coursewave.analytics.cache_seconds' => 0]);
    }

    public function test_the_instructor_dashboard_widgets_render(): void
    {
        $instructor = $this->userWithRole('instructor');
        $this->publishedCourse($instructor);
        $this->actingAs($instructor);
        Filament::setCurrentPanel(Filament::getPanel('instructor'));

        Livewire::test(InstructorWeekStats::class)
            ->assertSuccessful()
            ->assertSee('Earned (7d)')
            ->assertSee('No activity in either period');

        Livewire::test(InstructorAttention::class)
            ->assertSuccessful()
            ->assertSee('Needs your attention')
            ->assertSee('Set up your payout account')
            ->assertSee('Open full analytics');
    }

    public function test_the_admin_dashboard_widgets_render(): void
    {
        $admin = $this->userWithRole('admin');
        $this->actingAs($admin);
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        Livewire::test(PlatformWeekStats::class)
            ->assertSuccessful()
            ->assertSee('Gross sales (7d)')
            ->assertSee('Cancellations (7d)');

        Livewire::test(PlatformAttention::class)
            ->assertSuccessful()
            ->assertSee('Needs attention')
            ->assertSee('All clear')
            ->assertSee('Open platform analytics');
    }
}
