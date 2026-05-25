<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('financial_years') || ! Schema::hasColumn('financial_years', 'company_id')) {
            return;
        }

        // Existing project database already has company_id as NOT NULL.
        // Financial Year must be creatable before Company Setup, so company_id must allow NULL.
        if (DB::getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE financial_years MODIFY company_id BIGINT UNSIGNED NULL');
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('financial_years') || ! Schema::hasColumn('financial_years', 'company_id')) {
            return;
        }

        // Only revert when there is no NULL company_id, otherwise rollback would fail.
        $hasNullCompany = DB::table('financial_years')
            ->whereNull('company_id')
            ->exists();

        if (! $hasNullCompany && DB::getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE financial_years MODIFY company_id BIGINT UNSIGNED NOT NULL');
        }
    }
};