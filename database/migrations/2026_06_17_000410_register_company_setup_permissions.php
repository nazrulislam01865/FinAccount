<?php

use App\Support\AccountingRbac;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        AccountingRbac::syncAllCompanies(false);
    }

    public function down(): void
    {
        if (! Schema::hasTable('accounting_permissions')) {
            return;
        }

        $keys = [
            'company_setup.view', 'company_setup.manage',
            'business_types.view', 'business_types.manage',
            'currencies.view', 'currencies.manage',
            'time_zones.view', 'time_zones.manage',
            'financial_years.view', 'financial_years.manage',
        ];

        $ids = DB::table('accounting_permissions')->whereIn('key', $keys)->pluck('id');
        DB::table('accounting_user_permissions')->whereIn('permission_id', $ids)->delete();
        DB::table('accounting_role_permissions')->whereIn('permission_id', $ids)->delete();
        DB::table('accounting_permissions')->whereIn('id', $ids)->delete();
    }
};
