<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->widenRuntimePostingColumns();
        $this->syncVoucherRuleLastNumbers();
    }

    public function down(): void
    {
        // Production repair migration. Do not shrink data on rollback.
    }

    private function widenRuntimePostingColumns(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        if (Schema::hasTable('voucher_numbering_rules')) {
            DB::statement('ALTER TABLE voucher_numbering_rules MODIFY voucher_type VARCHAR(100) NOT NULL');
            DB::statement('ALTER TABLE voucher_numbering_rules MODIFY prefix VARCHAR(20) NOT NULL');
            DB::statement("ALTER TABLE voucher_numbering_rules MODIFY status VARCHAR(30) NOT NULL DEFAULT 'Active'");
        }

        if (Schema::hasTable('ledger_mapping_rules') && Schema::hasColumn('ledger_mapping_rules', 'party_ledger_effect')) {
            DB::statement("ALTER TABLE ledger_mapping_rules MODIFY party_ledger_effect VARCHAR(100) NOT NULL DEFAULT 'No Effect'");
        }

        if (Schema::hasTable('voucher_headers')) {
            if (Schema::hasColumn('voucher_headers', 'party_ledger_effect')) {
                DB::statement("ALTER TABLE voucher_headers MODIFY party_ledger_effect VARCHAR(100) NOT NULL DEFAULT 'No Effect'");
            }

            if (Schema::hasColumn('voucher_headers', 'cash_bank_effect')) {
                DB::statement("ALTER TABLE voucher_headers MODIFY cash_bank_effect VARCHAR(100) NOT NULL DEFAULT 'No Cash/Bank'");
            }
        }
    }

    private function syncVoucherRuleLastNumbers(): void
    {
        if (!Schema::hasTable('voucher_numbering_rules') || !Schema::hasTable('voucher_headers')) {
            return;
        }

        $rules = DB::table('voucher_numbering_rules')->get();

        foreach ($rules as $rule) {
            $query = DB::table('voucher_headers')
                ->where('financial_year_id', $rule->financial_year_id)
                ->where('voucher_type', $rule->voucher_type);

            if (property_exists($rule, 'company_id')) {
                if ($rule->company_id === null) {
                    $query->whereNull('company_id');
                } else {
                    $query->where('company_id', $rule->company_id);
                }
            }

            $maxUsedNumber = $query
                ->pluck('voucher_number')
                ->map(function ($voucherNumber) {
                    if (!preg_match('/(\d+)(?!.*\d)/', (string) $voucherNumber, $matches)) {
                        return 0;
                    }

                    return (int) $matches[1];
                })
                ->max() ?: 0;

            DB::table('voucher_numbering_rules')
                ->where('id', $rule->id)
                ->update([
                    'last_number' => max((int) ($rule->last_number ?? 0), (int) $maxUsedNumber),
                    'status' => 'Active',
                    'updated_at' => now(),
                ]);
        }
    }
};
