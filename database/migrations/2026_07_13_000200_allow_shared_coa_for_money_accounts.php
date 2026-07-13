<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('money_accounts', function (Blueprint $table): void {
            $table->dropUnique('money_company_coa_unique');
            $table->index(['company_id', 'chart_of_account_id'], 'money_company_coa_index');
        });
    }

    public function down(): void
    {
        Schema::table('money_accounts', function (Blueprint $table): void {
            $table->dropIndex('money_company_coa_index');
            $table->unique(['company_id', 'chart_of_account_id'], 'money_company_coa_unique');
        });
    }
};
