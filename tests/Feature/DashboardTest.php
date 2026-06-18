<?php

namespace Tests\Feature;

use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();

        parent::tearDown();
    }

    public function test_guests_are_redirected_to_the_login_page(): void
    {
        $response = $this->get(route('dashboard'));
        $response->assertRedirect(route('login'));
    }

    public function test_authenticated_users_can_visit_the_complete_business_health_dashboard(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $response = $this->get(route('dashboard'));

        $response
            ->assertOk()
            ->assertSee('Business Health Dashboard')
            ->assertSee('Available Money')
            ->assertSee('Quick Alerts')
            ->assertSee('Recent Transactions')
            ->assertSee('Financial Statement Snapshot')
            ->assertSee('data-test="system-admin-logout-button"', false)
            ->assertSee(route('logout'), false);
    }

    public function test_dashboard_accepts_a_supported_period_filter(): void
    {
        CarbonImmutable::setTestNow('2026-06-17 12:00:00');
        $user = User::factory()->create();
        $this->actingAs($user);

        $response = $this->get(route('dashboard', ['period' => 'week']));

        $response
            ->assertOk()
            ->assertSee('This Week · 15 Jun – 21 Jun 2026')
            ->assertSee('value="week" selected', false);
    }
}
