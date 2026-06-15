<?php

namespace Tests\Feature\Auth;

use Tests\TestCase;

class LoginScreenTest extends TestCase
{
    public function test_login_screen_uses_hisebghor_branding_and_keeps_login_controls(): void
    {
        $response = $this->get(route('login'));

        $response->assertOk();
        $response->assertSee('HisebGhor');
        $response->assertSee('Sign in to HisebGhor');
        $response->assertSee('Email address');
        $response->assertSee('Password');
        $response->assertSee('Keep me signed in on this device');
        $response->assertSee('Sign in securely');
        $response->assertSee('Forgot password?');
    }
}
