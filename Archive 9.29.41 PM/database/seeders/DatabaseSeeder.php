<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            MasterDataSeeder::class,
            RolePermissionSeeder::class,
            AdminUserSeeder::class,
            ChartOfAccountSeeder::class,
            SettlementTypeSeeder::class,
            TransactionHeadSeeder::class,
        ]);
    }
}
