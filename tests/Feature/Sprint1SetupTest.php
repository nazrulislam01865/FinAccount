<?php

namespace Tests\Feature;

use App\Models\AccountType;
use App\Models\Currency;
use App\Models\TimeZone;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Sprint1SetupTest extends TestCase
{
    use RefreshDatabase;

    public function test_company_setup_requires_required_fields(): void
    {
        $user = User::factory()->create();
        $response = $this->actingAs($user)->post(route('setup.company.store'), []);
        $response->assertSessionHasErrors(['company_name', 'short_name', 'currency_id', 'time_zone_id']);
    }

    public function test_chart_of_accounts_page_loads(): void
    {
        $this->seed();
        $user = User::factory()->create();
        $response = $this->actingAs($user)->get(route('setup.chart-of-accounts.index'));
        $response->assertOk();
    }
}
