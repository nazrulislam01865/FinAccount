<?php

namespace Tests\Feature\Landing;

use App\Models\LandingAdminUser;
use App\Models\LandingPageInquiry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
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
            ->assertDontSee(route('landing-admin.login'), false)
            ->assertDontSee('landing-system-login', false)
            ->assertDontSee('landing-admin-entry', false)
            ->assertDontSee('data-en="System Login"', false)
            ->assertSee('aspect-ratio:16/9', false)
            ->assertSee('object-fit:contain', false)
            ->assertSee('Standard Cloud')
            ->assertSee('৳70,000 – ৳95,000')
            ->assertSee('Important Notes')
            ->assertSee('আপনার ব্যবসার জন্য HisebGhor কি উপযোগী?')
            ->assertSee('landingCaptchaModal', false)
            ->assertDontSee('recommended-ribbon', false)
            ->assertDontSee('★ Recommended');
    }

    public function test_feature_screen_upload_accepts_any_image_ratio(): void
    {
        $admin = LandingAdminUser::query()->create([
            'name' => 'Landing Admin',
            'username' => 'landingadmin',
            'email' => 'landing@example.com',
            'password' => Hash::make('StrongPassword@123'),
            'status' => 'Active',
        ]);

        $this->actingAs($admin, 'landing_admin')
            ->withSession(['landing_admin_last_activity_at' => time()])
            ->put(route('landing-admin.update'), [
                'active_section' => 'features',
                'is_published' => '1',
                'screens' => [[
                    'image' => new UploadedFile(
                        base_path('tests/Fixtures/landing-screen-not-16x9.png'),
                        'square-or-portrait-image.png',
                        'image/png',
                        null,
                        true
                    ),
                ]],
            ])
            ->assertSessionDoesntHaveErrors(['screens.0.image']);
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
            ->assertSee('Landing Page Admin Dashboard')
            ->assertSee('data-test="landing-admin-logout-button"', false)
            ->assertSee(route('landing-admin.logout'), false);


        $this->get(route('landing-admin.edit', ['section' => 'pricing']))
            ->assertOk()
            ->assertSee('Implementation Packages & Pricing')
            ->assertSee('Installation Fee')
            ->assertSee('Maintenance Fee')
            ->assertSee('Server Hosting')
            ->assertSee('Important Note Cards')
            ->assertDontSee('Recommended Package?')
            ->assertDontSee('Top Ribbon Bangla')
            ->assertDontSee('Top Ribbon English');
    }

    public function test_active_landing_admin_can_logout(): void
    {
        $admin = LandingAdminUser::query()->create([
            'name' => 'Landing Admin',
            'username' => 'landingadmin',
            'email' => 'landing@example.com',
            'password' => Hash::make('StrongPassword@123'),
            'status' => 'Active',
        ]);

        $response = $this
            ->actingAs($admin, 'landing_admin')
            ->withSession(['landing_admin_last_activity_at' => time()])
            ->post(route('landing-admin.logout'));

        $response
            ->assertRedirect(route('landing-admin.login'))
            ->assertSessionHas('status', 'You have been logged out successfully.')
            ->assertSessionMissing('landing_admin_last_activity_at');

        $this->assertGuest('landing_admin');
    }

    public function test_public_inquiry_is_saved(): void
    {
        $captcha = $this->postJson(route('landing.captcha.challenge'))
            ->assertOk()
            ->json('data');

        preg_match('/(\d+) ([+-]) (\d+) = \?/', $captcha['challenge'], $matches);
        $answer = $matches[2] === '+'
            ? (int) $matches[1] + (int) $matches[3]
            : (int) $matches[1] - (int) $matches[3];

        $this->post(route('landing.inquiries.store'), [
            'name' => 'Demo Customer',
            'business_name' => 'Demo Business',
            'mobile' => '01700000000',
            'email' => 'demo@example.com',
            'message' => 'Please arrange a demo.',
            'captcha_token' => $captcha['token'],
            'captcha_answer' => (string) $answer,
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('landing_page_inquiries', [
            'name' => 'Demo Customer',
            'status' => LandingPageInquiry::STATUS_NEW,
        ]);
    }
    public function test_public_inquiry_rejects_an_invalid_captcha_answer(): void
    {
        $captcha = $this->postJson(route('landing.captcha.challenge'))
            ->assertOk()
            ->json('data');

        $this->postJson(route('landing.inquiries.store'), [
            'name' => 'Blocked Bot',
            'business_name' => 'Invalid Request',
            'mobile' => '01700000000',
            'captcha_token' => $captcha['token'],
            'captcha_answer' => '9999',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['captcha_answer']);

        $this->assertDatabaseMissing('landing_page_inquiries', [
            'name' => 'Blocked Bot',
        ]);
    }

}
