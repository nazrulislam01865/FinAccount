<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cash_bank_accounts', function (Blueprint $table) {
            // PRD defines Bank Name as user-entered text; keep bank_id nullable for old seeded data.
            if (!Schema::hasColumn('cash_bank_accounts', 'bank_name')) {
                $table->string('bank_name')->nullable()->after('bank_id');
            }
        });

        if (Schema::hasTable('transaction_heads') && DB::getDriverName() === 'mysql') {
            // The QA data sheet uses Expense and Journal natures, so VARCHAR keeps the PRD field extensible.
            DB::statement('ALTER TABLE transaction_heads MODIFY nature VARCHAR(100) NOT NULL');
        }

        if (Schema::hasTable('ledger_mapping_rules') && DB::getDriverName() === 'mysql') {
            // Party Ledger Effect is a PRD dropdown without a fixed value list; VARCHAR allows master-data variations.
            DB::statement("ALTER TABLE ledger_mapping_rules MODIFY party_ledger_effect VARCHAR(100) NOT NULL DEFAULT 'No Effect'");
        }
    }

    public function down(): void
    {
        Schema::table('cash_bank_accounts', function (Blueprint $table) {
            if (Schema::hasColumn('cash_bank_accounts', 'bank_name')) {
                $table->dropColumn('bank_name');
            }
        });

        if (Schema::hasTable('transaction_heads') && DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE transaction_heads MODIFY nature ENUM('Payment', 'Receipt', 'Due', 'Advance', 'Adjustment') NOT NULL");
        }

        if (Schema::hasTable('ledger_mapping_rules') && DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE ledger_mapping_rules MODIFY party_ledger_effect ENUM('No Effect', 'Increase Liability', 'Decrease Liability', 'Increase Receivable', 'Decrease Receivable', 'Increase Advance Asset', 'Decrease Advance Asset', 'Increase Advance Liability', 'Decrease Advance Liability') NOT NULL DEFAULT 'No Effect'");
        }
    }
};
