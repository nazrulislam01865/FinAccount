<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('cash_bank_accounts')) {
            return;
        }

        if (!Schema::hasColumn('cash_bank_accounts', 'account_number')) {
            return;
        }

        if (DB::getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE cash_bank_accounts MODIFY account_number VARCHAR(100) NULL');
        }
    }

    public function down(): void
    {
        if (!Schema::hasTable('cash_bank_accounts')) {
            return;
        }

        if (!Schema::hasColumn('cash_bank_accounts', 'account_number')) {
            return;
        }

        if (DB::getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE cash_bank_accounts MODIFY account_number VARCHAR(13) NULL');
        }
    }
};
