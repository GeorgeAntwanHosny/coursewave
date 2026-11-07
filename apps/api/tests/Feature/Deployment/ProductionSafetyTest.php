<?php

namespace Tests\Feature\Deployment;

use App\Models\User;
use Database\Seeders\AnalyticsDemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** The Docker setup runs with APP_ENV=production. These prove the developer-only tools stay off there. */
class ProductionSafetyTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_analytics_demo_seeder_refuses_to_run_in_production(): void
    {
        $this->app['env'] = 'production';

        $this->artisan('db:seed', ['--class' => AnalyticsDemoSeeder::class, '--force' => true])
            ->expectsOutputToContain('only runs when APP_ENV is local or testing')
            ->assertExitCode(0);

        $this->assertSame(0, User::where('email', 'like', 'demo.student%')->count());
    }

    public function test_the_test_notification_command_refuses_to_run_in_production(): void
    {
        $this->app['env'] = 'production';

        $this->artisan('notifications:test', ['email' => 'nobody@example.com'])
            ->expectsOutputToContain('only runs when APP_ENV is local or testing')
            ->assertExitCode(1);
    }
}
