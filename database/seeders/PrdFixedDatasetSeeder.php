<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class PrdFixedDatasetSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function (): void {
            $companyId = $this->company();
            $financialYearId = $this->financialYear($companyId);
            $settlements = $this->settlementTypes();
            $partyTypes = $this->partyTypes();
            $accounts = $this->chartOfAccounts($companyId);
            $parties = $this->parties($companyId, $partyTypes, $accounts);
            $heads = $this->transactionHeads($companyId, $partyTypes, $settlements);
            $this->voucherNumbering($companyId, $financialYearId);

            $this->postVoucher($companyId, $financialYearId, $heads['opening'], $settlements['journal'], null, 'OP-2026-00001', 'Opening Balance', '2026-01-01', 250000, [
                [$accounts['cash'], null, 'Debit', 50000, 0, 'Opening cash balance'],
                [$accounts['bank'], null, 'Debit', 100000, 0, 'Opening bank balance'],
                [$accounts['ar'], $parties['customer'], 'Debit', 20000, 0, 'Opening customer receivable'],
                [$accounts['inventory'], null, 'Debit', 80000, 0, 'Opening inventory balance'],
                [$accounts['ap'], $parties['supplier'], 'Credit', 0, 30000, 'Opening supplier payable'],
                [$accounts['capital'], null, 'Credit', 0, 220000, 'Opening owner capital'],
            ]);

            $this->postVoucher($companyId, $financialYearId, $heads['cash_expense'], $settlements['cash'], null, 'PV-2026-00001', 'Payment Voucher', '2026-01-05', 5000, [
                [$accounts['fuel'], null, 'Debit', 5000, 0, 'Fuel for farm delivery'],
                [$accounts['cash'], null, 'Credit', 0, 5000, 'Paid from cash'],
            ]);

            $this->postVoucher($companyId, $financialYearId, $heads['customer_invoice'], $settlements['due'], $parties['customer'], 'JV-2026-00001', 'Journal Voucher', '2026-01-07', 15000, [
                [$accounts['ar'], $parties['customer'], 'Debit', 15000, 0, 'Customer invoice'],
                [$accounts['sales'], $parties['customer'], 'Credit', 0, 15000, 'Seed sales revenue'],
            ]);

            $this->postVoucher($companyId, $financialYearId, $heads['customer_payment'], $settlements['bank'], $parties['customer'], 'RV-2026-00001', 'Receipt Voucher', '2026-01-10', 10000, [
                [$accounts['bank'], $parties['customer'], 'Debit', 10000, 0, 'Received in bank'],
                [$accounts['ar'], $parties['customer'], 'Credit', 0, 10000, 'Customer payment received'],
            ]);

            $this->postVoucher($companyId, $financialYearId, $heads['supplier_bill'], $settlements['due'], $parties['supplier'], 'JV-2026-00002', 'Journal Voucher', '2026-01-12', 8000, [
                [$accounts['purchase'], $parties['supplier'], 'Debit', 8000, 0, 'Supplier bill expense'],
                [$accounts['ap'], $parties['supplier'], 'Credit', 0, 8000, 'Supplier payable'],
            ]);

            $this->postVoucher($companyId, $financialYearId, $heads['supplier_payment'], $settlements['bank'], $parties['supplier'], 'PV-2026-00002', 'Payment Voucher', '2026-01-15', 20000, [
                [$accounts['ap'], $parties['supplier'], 'Debit', 20000, 0, 'Supplier payment'],
                [$accounts['bank'], $parties['supplier'], 'Credit', 0, 20000, 'Paid from bank'],
            ]);
        });
    }

    private function company(): int
    {
        $businessTypeId = $this->simpleLookup('business_types', ['name' => 'Small Farm and Seed Store', 'code' => 'FARM_SEED'], ['status' => 'Active']);
        $currencyId = $this->simpleLookup('currencies', ['name' => 'Bangladeshi Taka', 'code' => 'BDT'], ['symbol' => 'BDT', 'status' => 'Active']);
        $timeZoneId = $this->simpleLookup('time_zones', ['name' => 'Asia/Dhaka', 'code' => 'Asia/Dhaka'], ['status' => 'Active']);

        $payload = [
            'company_name' => 'Rahman Agro & Seed Store',
            'short_name' => 'Rahman Agro',
            'business_type_id' => $businessTypeId,
            'currency_id' => $currencyId,
            'time_zone_id' => $timeZoneId,
            'financial_year_start' => '2026-01-01',
            'financial_year_end' => '2026-12-31',
            'address' => 'Gazipur, Bangladesh',
            'contact_phone' => '017XXXXXXXX',
            'contact_email' => 'accounts@example.com',
        ];

        foreach ([
            'business_type' => 'Small Farm and Seed Store',
            'base_currency' => 'BDT',
            'accounting_method' => 'Accrual',
            'status' => 'Active',
        ] as $column => $value) {
            if (Schema::hasColumn('companies', $column)) {
                $payload[$column] = $value;
            }
        }

        return $this->upsertAndId('companies', ['company_name' => 'Rahman Agro & Seed Store'], $payload);
    }

    private function financialYear(int $companyId): int
    {
        return $this->upsertAndId('financial_years', ['company_id' => $companyId, 'name' => 'FY 2026'], [
            'start_date' => '2026-01-01',
            'end_date' => '2026-12-31',
            'status' => 'Open',
            'is_active' => true,
            'is_current' => true,
        ]);
    }

    private function settlementTypes(): array
    {
        return [
            'cash' => $this->simpleLookup('settlement_types', ['name' => 'Cash', 'code' => 'CASH'], ['status' => 'Active', 'sort_order' => 1]),
            'bank' => $this->simpleLookup('settlement_types', ['name' => 'Bank', 'code' => 'BANK'], ['status' => 'Active', 'sort_order' => 2]),
            'due' => $this->simpleLookup('settlement_types', ['name' => 'Due', 'code' => 'DUE'], ['status' => 'Active', 'sort_order' => 3]),
            'journal' => $this->simpleLookup('settlement_types', ['name' => 'Journal', 'code' => 'JOURNAL'], ['status' => 'Active', 'sort_order' => 4]),
        ];
    }

    private function partyTypes(): array
    {
        return [
            'customer' => $this->simpleLookup('party_types', ['name' => 'Customer', 'code' => 'CUSTOMER'], ['status' => 'Active', 'sort_order' => 1]),
            'supplier' => $this->simpleLookup('party_types', ['name' => 'Supplier', 'code' => 'SUPPLIER'], ['status' => 'Active', 'sort_order' => 2]),
        ];
    }

    private function chartOfAccounts(int $companyId): array
    {
        $types = [
            'Asset' => $this->accountType('Asset', 'ASSET', 'Debit', 1),
            'Liability' => $this->accountType('Liability', 'LIABILITY', 'Credit', 2),
            'Equity' => $this->accountType('Equity', 'EQUITY', 'Credit', 3),
            'Income' => $this->accountType('Income', 'INCOME', 'Credit', 4),
            'Expense' => $this->accountType('Expense', 'EXPENSE', 'Debit', 5),
        ];

        $rows = [
            'cash' => ['1110', 'Cash in Hand', 'Asset', 'Cash', true, false],
            'bank' => ['1130', 'Bank Account', 'Asset', 'Bank', true, false],
            'ar' => ['1150', 'Accounts Receivable', 'Asset', 'Party Control', false, true],
            'inventory' => ['1210', 'Inventory / Stock', 'Asset', 'Inventory', false, false],
            'ap' => ['2110', 'Accounts Payable', 'Liability', 'Party Control', false, true],
            'capital' => ['3010', 'Owner Capital', 'Equity', 'Equity', false, false],
            'sales' => ['4010', 'Sales Revenue', 'Income', 'Income', false, false],
            'purchase' => ['5010', 'Purchase Cost', 'Expense', 'Expense', false, false],
            'fuel' => ['6110', 'Fuel Expense', 'Expense', 'Expense', false, false],
        ];

        $ids = [];
        foreach ($rows as $key => [$code, $name, $class, $ledgerType, $isCashBank, $isPartyControl]) {
            $ids[$key] = $this->upsertAndId('chart_of_accounts', ['company_id' => $companyId, 'account_code' => $code], [
                'account_name' => $name,
                'account_type_id' => $types[$class],
                'account_level' => 'Ledger',
                'coa_level' => 4,
                'account_nature' => $class,
                'normal_balance' => in_array($class, ['Asset', 'Expense'], true) ? 'Debit' : 'Credit',
                'posting_allowed' => true,
                'is_cash_bank' => $isCashBank,
                'is_party_control' => $isPartyControl,
                'is_system_ledger' => true,
                'is_user_selectable' => true,
                'ledger_type' => $ledgerType,
                'status' => 'Active',
            ]);
        }

        DB::table('party_types')->where('id', $this->simpleLookup('party_types', ['name' => 'Customer', 'code' => 'CUSTOMER'], ['status' => 'Active']))->update(['default_ledger_account_id' => $ids['ar']]);
        DB::table('party_types')->where('id', $this->simpleLookup('party_types', ['name' => 'Supplier', 'code' => 'SUPPLIER'], ['status' => 'Active']))->update(['default_ledger_account_id' => $ids['ap']]);

        return $ids;
    }

    private function parties(int $companyId, array $partyTypes, array $accounts): array
    {
        return [
            'customer' => $this->upsertAndId('parties', ['party_code' => 'CUS-PRD-001'], [
                'company_id' => $companyId,
                'party_name' => 'Karim Agro Farm',
                'party_type_id' => $partyTypes['customer'],
                'linked_ledger_account_id' => $accounts['ar'],
                'default_ledger_nature' => 'Receivable',
                'opening_balance' => 0,
                'status' => 'Active',
            ]),
            'supplier' => $this->upsertAndId('parties', ['party_code' => 'SUP-PRD-001'], [
                'company_id' => $companyId,
                'party_name' => 'Green Seeds Supplier',
                'party_type_id' => $partyTypes['supplier'],
                'linked_ledger_account_id' => $accounts['ap'],
                'default_ledger_nature' => 'Payable',
                'opening_balance' => 0,
                'status' => 'Active',
            ]),
        ];
    }

    private function transactionHeads(int $companyId, array $partyTypes, array $settlements): array
    {
        $rows = [
            'opening' => ['Opening Balance', 'Adjustment', null, false],
            'cash_expense' => ['Cash Expense', 'Payment', null, false],
            'customer_invoice' => ['Customer Invoice / Due Sales', 'Due', $partyTypes['customer'], true],
            'customer_payment' => ['Customer Payment Received', 'Receipt', $partyTypes['customer'], true],
            'supplier_bill' => ['Supplier Bill / Due Expense', 'Due', $partyTypes['supplier'], true],
            'supplier_payment' => ['Supplier Payment', 'Payment', $partyTypes['supplier'], true],
        ];

        $ids = [];
        foreach ($rows as $key => [$name, $nature, $partyTypeId, $requiresParty]) {
            $ids[$key] = $this->upsertAndId('transaction_heads', ['company_id' => $companyId, 'name' => $name], [
                'nature' => $nature,
                'default_party_type_id' => $partyTypeId,
                'requires_party' => $requiresParty,
                'requires_reference' => false,
                'status' => 'Active',
            ]);
        }

        foreach ($ids as $key => $headId) {
            foreach ($settlements as $settlementId) {
                DB::table('settlement_type_transaction_head')->updateOrInsert([
                    'transaction_head_id' => $headId,
                    'settlement_type_id' => $settlementId,
                ], ['created_at' => now(), 'updated_at' => now()]);
            }
        }

        return $ids;
    }

    private function voucherNumbering(int $companyId, int $financialYearId): void
    {
        foreach ([
            ['Opening Balance', 'OP'],
            ['Payment Voucher', 'PV'],
            ['Receipt Voucher', 'RV'],
            ['Journal Voucher', 'JV'],
        ] as [$type, $prefix]) {
            $payload = [
                'prefix' => $prefix,
                'financial_year_id' => $financialYearId,
                'starting_number' => 1,
                'current_number' => 1,
                'number_length' => 5,
                'reset_frequency' => 'Yearly',
                'status' => 'Active',
            ];
            if (Schema::hasColumn('voucher_numbering_rules', 'reset_every_year')) {
                $payload['reset_every_year'] = true;
            }
            $payload = $this->onlyExistingColumns('voucher_numbering_rules', $payload);
            DB::table('voucher_numbering_rules')->updateOrInsert([
                'company_id' => $companyId,
                'voucher_type' => $type,
            ], $this->withTimestamps($payload, 'voucher_numbering_rules'));
        }
    }

    private function postVoucher(int $companyId, int $financialYearId, int $headId, int $settlementId, ?int $partyId, string $voucherNo, string $voucherType, string $date, float $amount, array $lines): void
    {
        $totalDebit = array_sum(array_column($lines, 3));
        $totalCredit = array_sum(array_column($lines, 4));

        $voucherId = $this->upsertAndId('voucher_headers', ['voucher_number' => $voucherNo], [
            'company_id' => $companyId,
            'financial_year_id' => $financialYearId,
            'voucher_type' => $voucherType,
            'voucher_date' => $date,
            'transaction_head_id' => $headId,
            'settlement_type_id' => $settlementId,
            'party_id' => $partyId,
            'amount' => $amount,
            'total_debit' => $totalDebit,
            'total_credit' => $totalCredit,
            'party_ledger_effect' => 'No Effect',
            'cash_bank_effect' => 'No Cash/Bank',
            'reference' => 'PRD-FIXED-DATASET',
            'notes' => 'PRD fixed dataset generated entry',
            'status' => 'Posted',
            'posted_at' => now(),
        ]);

        DB::table('voucher_details')->where('voucher_header_id', $voucherId)->delete();
        $lineNo = 1;
        foreach ($lines as [$ledgerId, $linePartyId, $entryType, $debit, $credit, $narration]) {
            DB::table('voucher_details')->insert($this->withTimestamps([
                'voucher_header_id' => $voucherId,
                'line_no' => $lineNo++,
                'account_id' => $ledgerId,
                'party_id' => $linePartyId,
                'entry_type' => $entryType,
                'debit' => $debit,
                'credit' => $credit,
                'narration' => $narration,
            ]));
        }

        $journalId = $this->upsertAndId('journal_headers', ['voucher_header_id' => $voucherId], [
            'company_id' => $companyId,
            'financial_year_id' => $financialYearId,
            'journal_no' => 'JE-' . $voucherNo,
            'voucher_number' => $voucherNo,
            'voucher_type' => $voucherType,
            'source_type' => 'PRD Fixed Dataset',
            'journal_date' => $date,
            'transaction_head_id' => $headId,
            'party_id' => $partyId,
            'amount' => $amount,
            'total_debit' => $totalDebit,
            'total_credit' => $totalCredit,
            'status' => 'Posted',
            'narration' => 'PRD fixed dataset generated entry',
            'posted_at' => now(),
        ]);

        DB::table('journal_lines')->where('journal_header_id', $journalId)->delete();
        $lineNo = 1;
        foreach ($lines as [$ledgerId, $linePartyId, $entryType, $debit, $credit, $narration]) {
            DB::table('journal_lines')->insert($this->withTimestamps([
                'journal_header_id' => $journalId,
                'line_no' => $lineNo++,
                'ledger_id' => $ledgerId,
                'party_id' => $linePartyId,
                'entry_type' => $entryType,
                'debit_amount' => $debit,
                'credit_amount' => $credit,
                'line_narration' => $narration,
            ]));
        }
    }

    private function accountType(string $name, string $code, string $normalBalance, int $sortOrder): int
    {
        return $this->simpleLookup('account_types', ['name' => $name, 'code' => $code], [
            'normal_balance' => $normalBalance,
            'status' => 'Active',
            'sort_order' => $sortOrder,
        ]);
    }

    private function simpleLookup(string $table, array $identity, array $attributes = []): int
    {
        return $this->upsertAndId($table, $identity, $attributes);
    }

    private function upsertAndId(string $table, array $identity, array $attributes = []): int
    {
        $existing = DB::table($table)->where($identity)->first();
        $payload = $this->onlyExistingColumns($table, array_merge($identity, $attributes));
        $payload = $this->withTimestamps($payload, $table);

        if ($existing) {
            DB::table($table)->where('id', $existing->id)->update($payload);
            return (int) $existing->id;
        }

        return (int) DB::table($table)->insertGetId($payload);
    }

    private function onlyExistingColumns(string $table, array $payload): array
    {
        return array_filter($payload, fn ($value, $column) => Schema::hasColumn($table, (string) $column), ARRAY_FILTER_USE_BOTH);
    }

    private function withTimestamps(array $payload, ?string $table = null): array
    {
        if ($table === null || Schema::hasColumn($table, 'created_at')) {
            $payload['created_at'] = $payload['created_at'] ?? now();
        }
        if ($table === null || Schema::hasColumn($table, 'updated_at')) {
            $payload['updated_at'] = now();
        }

        return $payload;
    }
}
