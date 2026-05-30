<?php

namespace Database\Seeders;

use App\Models\LandingAdminUser;
use App\Models\Role;
use App\Models\User;
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

        $email = Str::lower(trim((string) config('landing_admin.credentials.email', '')));
        $password = (string) config('landing_admin.credentials.password', '');
        $name = trim((string) config('landing_admin.credentials.name', 'Landing Page Admin')) ?: 'Landing Page Admin';

        if ($email === '' || $password === '') {
            Log::info('Landing Admin user was not seeded because LANDING_ADMIN_EMAIL and LANDING_ADMIN_PASSWORD are not configured.');
            return;
        }

        if (strlen($password) < 12) {
            Log::warning('Landing Admin user was not seeded because LANDING_ADMIN_PASSWORD must be at least 12 characters.');
            return;
        }

        LandingAdminUser::updateOrCreate(
            ['email' => $email],
            [
                'name' => $name,
                'password' => Hash::make($password),
                'status' => 'Active',
            ]
        );

        $legacyRole = Role::where('name', 'Landing Page Admin')->first();

        if ($legacyRole) {
            User::where('email', $email)->get()->each(function (User $user) use ($legacyRole) {
                $user->roles()->detach($legacyRole->id);
            });
        }
    }
}
