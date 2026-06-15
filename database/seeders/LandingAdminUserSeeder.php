<?php

namespace Database\Seeders;

use App\Models\LandingAdminUser;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class LandingAdminUserSeeder extends Seeder
{
    public function run(): void
    {
        if (! (bool) config('landing_admin.enabled', true)) {
            return;
        }

        $username = Str::lower(trim((string) config('landing_admin.credentials.username', '')));
        $password = (string) config('landing_admin.credentials.password', '');
        $name = trim((string) config('landing_admin.credentials.name', 'Landing Page Admin')) ?: 'Landing Page Admin';
        $configuredEmail = Str::lower(trim((string) config('landing_admin.credentials.email', '')));
        $email = $configuredEmail !== '' ? $configuredEmail : $username.'@hisebghor.test';

        if ($username === '' || $password === '') {
            Log::info('Landing Admin user was not seeded because LANDING_ADMIN_USERNAME and LANDING_ADMIN_PASSWORD are not configured.');
            return;
        }

        if (! preg_match('/^[a-z0-9._-]{3,100}$/', $username)) {
            Log::warning('Landing Admin user was not seeded because LANDING_ADMIN_USERNAME contains unsupported characters.');
            return;
        }

        if (strlen($password) < 12) {
            Log::warning('Landing Admin user was not seeded because LANDING_ADMIN_PASSWORD must be at least 12 characters.');
            return;
        }

        $admin = LandingAdminUser::query()
            ->where('username', $username)
            ->orWhere('email', $email)
            ->first() ?? new LandingAdminUser();

        $admin->fill([
            'name' => $name,
            'username' => $username,
            'email' => $email,
            'password' => Hash::make($password),
            'status' => 'Active',
        ])->save();
    }
}
