<?php

use App\Support\AccountingRbac;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('accounting_permissions') || ! Schema::hasTable('accounting_roles') || ! Schema::hasTable('accounting_role_permissions')) {
            return;
        }

        AccountingRbac::syncAllCompanies(false);
    }

    public function down(): void
    {
        // Keep permissions on rollback. Removing them can break existing role matrices.
    }
};
