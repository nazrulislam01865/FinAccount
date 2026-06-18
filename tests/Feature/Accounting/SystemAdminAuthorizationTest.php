<?php

namespace Tests\Feature\Accounting;

use App\Models\User;
use Database\Seeders\HisebGhorDemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SystemAdminAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_accounting_user_can_use_transactions_and_reports_but_not_configuration(): void
    {
        $this->seed(HisebGhorDemoSeeder::class);
        $admin = User::query()->where('email', 'admin@hisebghor.test')->firstOrFail();
        $user = User::factory()->accountingUser()->create([
            'company_id' => $admin->company_id,
        ]);

        $this->actingAs($user)->get(route('dashboard'))
            ->assertOk()
            ->assertDontSee('Configuration');
        $this->actingAs($user)->get(route('transactions.index'))->assertOk();
        $this->actingAs($user)->get(route('journal-entries.index'))->assertOk();
        $this->actingAs($user)->get(route('basic-statements.index'))->assertOk();

        $this->actingAs($user)->get(route('chart-of-accounts.index'))->assertForbidden();
        $this->actingAs($user)->get(route('accounting-rules.index'))->assertForbidden();
        $this->actingAs($user)->get(route('master.index', 'transaction-categories'))->assertForbidden();
        $this->actingAs($user)->post(route('dashboard.reset-demo'))->assertForbidden();
    }

    public function test_system_admin_can_manage_configuration(): void
    {
        $this->seed(HisebGhorDemoSeeder::class);
        $admin = User::query()->where('email', 'admin@hisebghor.test')->firstOrFail();

        $this->assertTrue($admin->isSystemAdmin());
        $this->actingAs($admin)->get(route('chart-of-accounts.index'))->assertOk();
        $this->actingAs($admin)->get(route('accounting-rules.index'))->assertOk();
        $this->actingAs($admin)->get(route('master.voucher-sequences.index'))->assertOk();
    }
}
