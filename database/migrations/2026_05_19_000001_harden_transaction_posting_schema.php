<?php

use App\Models\VoucherNumberingRule;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->widenMySqlEnumsUsedByPosting();
        $this->ensureVoucherNumberingRules();
    }

    public function down(): void
    {
        // This migration only hardens production data and widens enum columns.
        // It is intentionally not destructive on rollback.
    }

    private function widenMySqlEnumsUsedByPosting(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        if (Schema::hasTable('voucher_numbering_rules')) {
            DB::statement('ALTER TABLE voucher_numbering_rules MODIFY voucher_type VARCHAR(100) NOT NULL');
            DB::statement('ALTER TABLE voucher_numbering_rules MODIFY prefix VARCHAR(20) NOT NULL');
        }

        if (Schema::hasTable('ledger_mapping_rules') && Schema::hasColumn('ledger_mapping_rules', 'party_ledger_effect')) {
            DB::statement("ALTER TABLE ledger_mapping_rules MODIFY party_ledger_effect VARCHAR(100) NOT NULL DEFAULT 'No Effect'");
        }
    }

    private function ensureVoucherNumberingRules(): void
    {
        if (!Schema::hasTable('voucher_numbering_rules') || !Schema::hasTable('financial_years')) {
            return;
        }

        $financialYearIds = DB::table('financial_years')->pluck('id');
        $companyIds = Schema::hasTable('companies')
            ? DB::table('companies')->pluck('id')
            : collect([null]);

        if ($companyIds->isEmpty()) {
            $companyIds = collect([null]);
        }

        $rules = [
            ['voucher_type' => 'Payment Voucher', 'prefix' => 'PV', 'used_for' => 'Cash/bank payments'],
            ['voucher_type' => 'Receipt Voucher', 'prefix' => 'RV', 'used_for' => 'Cash/bank receipts'],
            ['voucher_type' => 'Journal Voucher', 'prefix' => 'JV', 'used_for' => 'Non-cash journal entries'],
            ['voucher_type' => 'Contra / Transfer Voucher', 'prefix' => 'CV', 'used_for' => 'Cash/bank transfers'],
            ['voucher_type' => 'Draft Voucher', 'prefix' => 'DR', 'used_for' => 'Unposted draft transactions'],
            ['voucher_type' => 'Opening Voucher', 'prefix' => 'OP', 'used_for' => 'Opening balance posting'],
        ];

        foreach ($companyIds as $companyId) {
            foreach ($financialYearIds as $financialYearId) {
                foreach ($rules as $rule) {
                    $this->upsertVoucherRule($companyId, (int) $financialYearId, $rule);
                }
            }
        }
    }

    private function upsertVoucherRule(mixed $companyId, int $financialYearId, array $rule): void
    {
        $query = DB::table('voucher_numbering_rules')
            ->where('financial_year_id', $financialYearId)
            ->where('voucher_type', $rule['voucher_type']);

        if ($companyId === null) {
            $query->whereNull('company_id');
        } else {
            $query->where('company_id', $companyId);
        }

        $existing = $query->first();
        $now = now();

        if ($existing) {
            DB::table('voucher_numbering_rules')
                ->where('id', $existing->id)
                ->update([
                    'prefix' => $rule['prefix'],
                    'format_template' => $rule['prefix'] . '-{YYYY}-{00000}',
                    'number_length' => 5,
                    'reset_every_year' => true,
                    'used_for' => $rule['used_for'],
                    'status' => 'Active',
                    'updated_at' => $now,
                ]);

            return;
        }

        DB::table('voucher_numbering_rules')->insert([
            'company_id' => $companyId,
            'financial_year_id' => $financialYearId,
            'voucher_type' => $rule['voucher_type'],
            'prefix' => $rule['prefix'],
            'format_template' => $rule['prefix'] . '-{YYYY}-{00000}',
            'starting_number' => 1,
            'number_length' => 5,
            'last_number' => 0,
            'reset_every_year' => true,
            'used_for' => $rule['used_for'],
            'status' => 'Active',
            'created_by' => null,
            'updated_by' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }
};
