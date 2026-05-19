<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->widenLedgerMappingPartyEffect();
        $this->backfillVoucherNumberingRules();
    }

    public function down(): void
    {
        // Data-preserving migration. Do not delete voucher numbering rules or shrink
        // party_ledger_effect, because existing transaction setup may already use
        // the wider values.
    }

    private function widenLedgerMappingPartyEffect(): void
    {
        if (!Schema::hasTable('ledger_mapping_rules') || !Schema::hasColumn('ledger_mapping_rules', 'party_ledger_effect')) {
            return;
        }

        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE ledger_mapping_rules MODIFY party_ledger_effect VARCHAR(100) NOT NULL DEFAULT 'No Effect'");
        }
    }

    private function backfillVoucherNumberingRules(): void
    {
        if (!Schema::hasTable('voucher_numbering_rules') || !Schema::hasTable('financial_years')) {
            return;
        }

        $rules = [
            ['Opening Voucher', 'OP', 'OP-{YYYY}-{00000}', 'Opening balance'],
            ['Payment Voucher', 'PV', 'PV-{YYYY}-{00000}', 'Cash/bank payments'],
            ['Receipt Voucher', 'RV', 'RV-{YYYY}-{00000}', 'Cash/bank receipts'],
            ['Journal Voucher', 'JV', 'JV-{YYYY}-{00000}', 'Due, adjustment, opening balance'],
            ['Contra / Transfer Voucher', 'CV', 'CV-{YYYY}-{00000}', 'Cash to bank or bank to bank transfer'],
            ['Draft Voucher', 'DR', 'DR-{YYYY}-{00000}', 'Unposted draft transactions'],
        ];

        $now = now();

        DB::table('financial_years')
            ->orderBy('id')
            ->get(['id', 'company_id'])
            ->each(function ($financialYear) use ($rules, $now): void {
                foreach ($rules as [$type, $prefix, $format, $usedFor]) {
                    $exists = DB::table('voucher_numbering_rules')
                        ->where('company_id', $financialYear->company_id)
                        ->where('financial_year_id', $financialYear->id)
                        ->where('voucher_type', $type)
                        ->exists();

                    if ($exists) {
                        continue;
                    }

                    DB::table('voucher_numbering_rules')->insert([
                        'company_id' => $financialYear->company_id,
                        'financial_year_id' => $financialYear->id,
                        'voucher_type' => $type,
                        'prefix' => $prefix,
                        'format_template' => $format,
                        'starting_number' => 1,
                        'number_length' => 5,
                        'last_number' => 0,
                        'reset_every_year' => true,
                        'used_for' => $usedFor,
                        'status' => 'Active',
                        'created_by' => null,
                        'updated_by' => null,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                }
            });
    }
};
