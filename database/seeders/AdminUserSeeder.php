<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        $superAdmin = Role::where('name', 'Super Admin')->first();

        if (!$superAdmin) {
            return;
        }

        // Promote the existing first user to Super Admin so current installations keep their login.
        $existingUser = User::query()->orderBy('id')->first();

        if (!$existingUser) {
            $existingUser = User::create([
                'name' => 'Super Admin',
                'email' => 'admin@example.com',
                'password' => Hash::make('password'),
                'status' => 'Active',
            ]);
        }

        $existingUser->forceFill(['status' => 'Active'])->save();
        $existingUser->roles()->syncWithoutDetaching([$superAdmin->id]);

        // If the previous seeded admin account exists separately, keep it usable as Super Admin too.
        $seededAdmin = User::where('email', 'admin@example.com')->first();
        if ($seededAdmin) {
            $seededAdmin->forceFill(['status' => 'Active'])->save();
            $seededAdmin->roles()->syncWithoutDetaching([$superAdmin->id]);
        }
    }
}
