<?php

namespace Tests\Feature\Landing;

use App\Models\LandingAdminUser;
use App\Models\LandingPageInquiry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class LandingPageModuleTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_landing_page_is_available(): void
    {
        $this->get(route('landing.show'))
            ->assertOk()
            ->assertSee('HisebGhor')
            ->assertSee(route('landing-admin.login'), false)
            ->assertSee(url('/login'), false);
    }

    public function test_landing_admin_login_page_is_available(): void
    {
        $this->get(route('landing-admin.login'))
            ->assertOk()
            ->assertSee('Landing Admin Login');
    }

    public function test_landing_admin_dashboard_requires_its_separate_guard(): void
    {
        $this->get(route('landing-admin.dashboard'))
            ->assertRedirect(route('landing-admin.login'));
    }

    public function test_active_landing_admin_can_log_in_and_open_dashboard(): void
    {
        $admin = LandingAdminUser::query()->create([
            'name' => 'Landing Admin',
            'username' => 'landingadmin',
            'email' => 'landing@example.com',
            'password' => Hash::make('StrongPassword@123'),
            'status' => 'Active',
        ]);

        $this->post(route('landing-admin.login.store'), [
            'username' => $admin->username,
            'password' => 'StrongPassword@123',
        ])->assertRedirect(route('landing-admin.dashboard', absolute: false));

        $this->assertAuthenticatedAs($admin, 'landing_admin');

        $this->get(route('landing-admin.dashboard'))
            ->assertOk()
            ->assertSee('Landing Page Admin Dashboard');
    }

    public function test_public_inquiry_is_saved(): void
    {
        $this->post(route('landing.inquiries.store'), [
            'name' => 'Demo Customer',
            'business_name' => 'Demo Business',
            'mobile' => '01700000000',
            'email' => 'demo@example.com',
            'message' => 'Please arrange a demo.',
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('landing_page_inquiries', [
            'name' => 'Demo Customer',
            'status' => LandingPageInquiry::STATUS_NEW,
        ]);
    }
}
