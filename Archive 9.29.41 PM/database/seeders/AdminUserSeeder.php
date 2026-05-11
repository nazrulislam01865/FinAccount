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
        $user = User::updateOrCreate(
            ['email' => 'admin@example.com'],
            ['name' => 'Admin User', 'password' => Hash::make('password'), 'status' => 'Active']
        );

        $admin = Role::where('name', 'Admin')->first();
        if ($admin) {
            $user->roles()->syncWithoutDetaching([$admin->id]);
        }
    }
}
