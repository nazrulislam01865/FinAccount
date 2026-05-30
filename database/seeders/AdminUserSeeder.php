<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        $superAdmin = Role::where('name', 'Super Admin')->first();

        if (! $superAdmin) {
            return;
        }

        $existingUser = User::query()->orderBy('id')->first();

        if (! $existingUser) {
            $existingUser = $this->createInitialAdminFromEnvironment();
        }

        if (! $existingUser) {
            Log::warning('No initial Super Admin user was seeded because ADMIN_EMAIL and ADMIN_PASSWORD are not configured.');
            return;
        }

        $existingUser->forceFill(['status' => 'Active'])->save();
        $existingUser->roles()->syncWithoutDetaching([$superAdmin->id]);

        $adminEmail = env('ADMIN_EMAIL');
        if ($adminEmail) {
            $seededAdmin = User::where('email', $adminEmail)->first();
            if ($seededAdmin) {
                $seededAdmin->forceFill(['status' => 'Active'])->save();
                $seededAdmin->roles()->syncWithoutDetaching([$superAdmin->id]);
            }
        }

    }

    private function createInitialAdminFromEnvironment(): ?User
    {
        $email = trim((string) env('ADMIN_EMAIL', ''));
        $password = (string) env('ADMIN_PASSWORD', '');
        $name = trim((string) env('ADMIN_NAME', 'Super Admin')) ?: 'Super Admin';

        if ($email === '' || $password === '') {
            return null;
        }

        if (strlen($password) < 12) {
            Log::warning('Initial admin user was not created because ADMIN_PASSWORD must be at least 12 characters.');
            return null;
        }

        return User::create([
            'name' => $name,
            'email' => Str::lower($email),
            'password' => Hash::make($password),
            'status' => 'Active',
        ]);
    }
}
