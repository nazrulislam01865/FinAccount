<?php

namespace Database\Seeders;

use App\Models\AccountType;
use App\Models\Bank;
use App\Models\BusinessType;
use App\Models\CashBankAccount;
use App\Models\ChartOfAccount;
use App\Models\Company;
use App\Models\Currency;
use App\Models\FinancialYear;
use App\Models\LedgerMappingRule;
use App\Models\OpeningBalance;
use App\Models\Party;
use App\Models\PartyType;
use App\Models\Role;
use App\Models\SettlementType;
use App\Models\TimeZone;
use App\Models\TransactionHead;
use App\Models\User;
use App\Models\VoucherHeader;
use App\Models\VoucherNumberingRule;
use App\Services\Setup\OpeningBalanceService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class HisebGhorQaDatasetSeeder extends Seeder
{

    public function run(): void
    {
        $this->call([
            MasterDataSeeder::class,
            RolePermissionSeeder::class,
            ChartOfAccountSeeder::class,
            SettlementTypeSeeder::class,
            TransactionHeadSeeder::class,
        ]);

        $admin = $this->adminUser();
        $company = $this->company($admin->id);
        $financialYear = $this->financialYear($company, $admin->id);

        $company->forceFill([
            'default_financial_year_id' => $financialYear->id,
            'financial_year_start' => $financialYear->start_date,
            'financial_year_end' => $financialYear->end_date,
            'status' => 'Active',
        ])->save();

        $this->hardenPostingLedgers($company->id, $admin->id);
        $this->cashBankAccounts($company->id, $admin->id);
        $this->voucherNumbering($company->id, $financialYear->id, $admin->id);
        $this->parties($company->id, $admin->id);

        // Re-run safe mapping/numbering seeders after company/FY exists.
        // Do NOT call AdvanceAccountingRuleSeeder here: the project DatabaseSeeder already calls it,
        // and on this project version it can update a different row to an existing rule_code
        // such as LM-ADV-001, causing a duplicate-key failure.
        $this->call([
            CashBankAccountSeeder::class,
            LedgerMappingRuleSeeder::class,
            VoucherNumberingRuleSeeder::class,
        ]);

        $this->postOpeningBalance($company, $financialYear, $admin->id);
    }

    private function adminUser(): User
    {
        $email = strtolower(trim((string) env('ADMIN_EMAIL', 'admin@example.com')));
        $password = (string) env('ADMIN_PASSWORD', 'AdminPassword123');
        $name = trim((string) env('ADMIN_NAME', 'Super Admin')) ?: 'Super Admin';

        $user = User::query()->updateOrCreate(
            ['email' => $email],
            [
                'name' => $name,
                'password' => Hash::make($password),
                'status' => 'Active',
                'email_verified_at' => now(),
            ]
        );

        $role = Role::query()->where('name', 'Super Admin')->first()
            ?: Role::query()->where('name', 'Admin')->first();

        if ($role) {
            $user->roles()->syncWithoutDetaching([$role->id]);
        }

        return $user;
    }

    private function company(int $userId): Company
    {
        $currency = Currency::query()->where('code', 'BDT')->first();
        $timeZone = TimeZone::query()->where('php_timezone', 'Asia/Dhaka')->first();
        $businessType = BusinessType::query()->where('code', 'TRADING')->first()
            ?: BusinessType::query()->first();

        return Company::query()->updateOrCreate(
            ['short_name' => 'HGQA'],
            [
                'company_name' => 'HisebGhor QA Trading',
                'business_type_id' => $businessType?->id,
                'currency_id' => $currency?->id,
                'time_zone_id' => $timeZone?->id,
                'accounting_method' => 'Accrual',
                'financial_year_start' => '2026-01-01',
                'financial_year_end' => '2026-12-31',
                'address' => 'Dhaka, Bangladesh',
                'contact_email' => 'qa@hisebghor.test',
                'contact_phone' => '01700000000',
                'journal_voucher_prefix' => 'JV',
                'payment_voucher_prefix' => 'PV',
                'receipt_voucher_prefix' => 'RV',
                'enable_multi_branch' => false,
                'status' => 'Active',
                'created_by' => $userId,
                'updated_by' => $userId,
            ]
        );
    }

    private function financialYear(Company $company, int $userId): FinancialYear
    {
        FinancialYear::query()
            ->where('company_id', $company->id)
            ->update(['is_current' => false, 'is_active' => false]);

        return FinancialYear::query()->updateOrCreate(
            [
                'company_id' => $company->id,
                'name' => 'FY 2026',
            ],
            [
                'start_date' => '2026-01-01',
                'end_date' => '2026-12-31',
                'lock_date' => null,
                'is_active' => true,
                'is_current' => true,
                'status' => 'Open',
                'created_by' => $userId,
                'updated_by' => $userId,
            ]
        );
    }

    private function hardenPostingLedgers(int $companyId, int $userId): void
    {
        $typeId = fn (string $name) => AccountType::query()->where('name', $name)->value('id');
        $partyTypeId = fn (string $name) => PartyType::query()->where('name', $name)->value('id');

        $definitions = [
            ['1000', 'Assets', 'Asset', 'Group', 1, false, false, false, null, 'Group'],
            ['1010', 'Cash in Hand', 'Asset', 'Ledger', 4, true, true, false, null, 'Cash'],
            ['1020', 'BRAC Bank Current Account', 'Asset', 'Ledger', 4, true, true, false, null, 'Bank'],
            ['1100', 'Accounts Receivable', 'Asset', 'Ledger', 4, false, true, true, 'Customer', 'Party Control'],
            ['1200', 'Advance to Supplier / Employee', 'Asset', 'Ledger', 4, false, true, true, 'Supplier', 'Party Control'],
            ['2000', 'Liabilities', 'Liability', 'Group', 1, false, false, false, null, 'Group'],
            ['2010', 'Accounts Payable', 'Liability', 'Ledger', 4, false, true, true, 'Supplier', 'Party Control'],
            ['2020', 'Salary Payable', 'Liability', 'Ledger', 4, false, true, false, null, 'Liability'],
            ['2030', 'Advance from Customer', 'Liability', 'Ledger', 4, false, true, true, 'Customer', 'Party Control'],
            ['3000', 'Equity', 'Equity', 'Group', 1, false, false, false, null, 'Group'],
            ['3010', 'Owner Capital', 'Equity', 'Ledger', 4, false, true, false, null, 'Equity'],
            ['4000', 'Income', 'Income', 'Group', 1, false, false, false, null, 'Group'],
            ['4010', 'Vehicle Rent Income', 'Income', 'Ledger', 4, false, true, false, null, 'Income'],
            ['4020', 'Service Income', 'Income', 'Ledger', 4, false, true, false, null, 'Income'],
            ['5000', 'Expenses', 'Expense', 'Group', 1, false, false, false, null, 'Group'],
            ['5010', 'Salary Expense', 'Expense', 'Ledger', 4, false, true, false, null, 'Expense'],
            ['5020', 'Fuel Expense', 'Expense', 'Ledger', 4, false, true, false, null, 'Expense'],
            ['5040', 'Office Rent Expense', 'Expense', 'Ledger', 4, false, true, false, null, 'Expense'],
        ];

        foreach ($definitions as [$code, $name, $typeName, $level, $coaLevel, $cashBank, $posting, $partyControl, $partyType, $ledgerType]) {
            $accountTypeId = $typeId($typeName);
            $normal = AccountType::query()->whereKey($accountTypeId)->value('normal_balance') ?: ($typeName === 'Asset' || $typeName === 'Expense' ? 'Debit' : 'Credit');

            ChartOfAccount::query()->updateOrCreate(
                ['account_code' => $code],
                [
                    'company_id' => null,
                    'account_name' => $name,
                    'account_level' => $level,
                    'coa_level' => $coaLevel,
                    'account_type_id' => $accountTypeId,
                    'account_nature' => $typeName,
                    'normal_balance' => $normal,
                    'is_cash_bank' => $cashBank,
                    'is_party_control' => $partyControl,
                    'party_type_id' => $partyType ? $partyTypeId($partyType) : null,
                    'is_system_ledger' => true,
                    'is_user_selectable' => true,
                    'posting_allowed' => $posting,
                    'ledger_type' => $ledgerType,
                    'status' => 'Active',
                    'created_by' => $userId,
                    'updated_by' => $userId,
                ]
            );
        }
    }

    private function cashBankAccounts(int $companyId, int $userId): void
    {
        $bank = Bank::query()->where('bank_name', 'BRAC Bank')->first();
        $accounts = [
            ['CB-001', 'Office Cash', 'Cash', '1010', null, null, null, null],
            ['BK-001', 'BRAC Bank', 'Bank', '1020', $bank?->id, 'BRAC Bank', 'Main Branch', '1000000000001'],
        ];

        foreach ($accounts as [$code, $name, $type, $ledgerCode, $bankId, $bankName, $branch, $number]) {
            $ledger = ChartOfAccount::query()->where('account_code', $ledgerCode)->first();
            if (! $ledger) {
                continue;
            }

            CashBankAccount::query()->updateOrCreate(
                ['cash_bank_name' => $name],
                [
                    'company_id' => $companyId,
                    'cash_bank_name' => $name,
                    'type' => $type,
                    'linked_ledger_account_id' => $ledger->id,
                    'bank_id' => $bankId,
                    'bank_name' => $bankName,
                    'branch_name' => $branch,
                    'account_number' => $number,
                    'opening_balance' => 0,
                    'usage_note' => 'QA default ' . strtolower($type) . ' account.',
                    'status' => 'Active',
                    'created_by' => $userId,
                    'updated_by' => $userId,
                ]
            );
        }
    }

    private function voucherNumbering(int $companyId, int $financialYearId, int $userId): void
    {
        $rules = [
            ['Opening Voucher', 'OP', 'Opening balance'],
            ['Payment Voucher', 'PV', 'Cash/bank payments'],
            ['Receipt Voucher', 'RV', 'Cash/bank receipts'],
            ['Journal Voucher', 'JV', 'Due and adjustment entries'],
            ['Contra / Transfer Voucher', 'CV', 'Cash/bank transfers'],
            ['Draft Voucher', 'DR', 'Draft entries'],
        ];

        foreach ($rules as [$type, $prefix, $usedFor]) {
            VoucherNumberingRule::query()->updateOrCreate(
                [
                    'company_id' => $companyId,
                    'financial_year_id' => $financialYearId,
                    'voucher_type' => $type,
                ],
                [
                    'prefix' => $prefix,
                    'format_template' => $prefix . '-{YYYY}-{00000}',
                    'starting_number' => 1,
                    'number_length' => 5,
                    'last_number' => 0,
                    'reset_every_year' => true,
                    'used_for' => $usedFor,
                    'status' => 'Active',
                    'created_by' => $userId,
                    'updated_by' => $userId,
                ]
            );
        }
    }

    private function parties(int $companyId, int $userId): void
    {
        $partyTypeId = fn (string $name) => PartyType::query()->where('name', $name)->value('id');
        $ledgerId = fn (string $code) => ChartOfAccount::query()->where('account_code', $code)->value('id');

        $parties = [
            ['CUS-QA-001', 'Karim Agro Farm', 'Customer', '1100', 'Receivable', '01710000001'],
            ['SUP-QA-001', 'Green Seed Supplier Ltd.', 'Supplier', '2010', 'Payable', '01710000002'],
            ['EMP-QA-001', 'QA Employee Rahim', 'Employee', '2020', 'Payable', '01710000003'],
        ];

        foreach ($parties as [$code, $name, $type, $ledgerCode, $nature, $mobile]) {
            Party::query()->updateOrCreate(
                ['party_code' => $code],
                [
                    'company_id' => $companyId,
                    'party_name' => $name,
                    'party_type_id' => $partyTypeId($type),
                    'mobile' => $mobile,
                    'email' => strtolower(str_replace([' ', '.'], ['.', ''], $name)) . '@example.test',
                    'address' => 'Dhaka, Bangladesh',
                    'linked_ledger_account_id' => $ledgerId($ledgerCode),
                    'default_ledger_nature' => $nature,
                    'opening_balance' => 0,
                    'opening_balance_type' => $nature === 'Receivable' ? 'Debit' : 'Credit',
                    'status' => 'Active',
                    'created_by' => $userId,
                    'updated_by' => $userId,
                ]
            );
        }
    }

    private function postOpeningBalance(Company $company, FinancialYear $financialYear, int $userId): void
    {
        if (OpeningBalance::query()->where('financial_year_id', $financialYear->id)->where('status', 'Final')->exists()) {
            return;
        }

        if (VoucherHeader::query()->where('voucher_type', 'Opening Voucher')->where('financial_year_id', $financialYear->id)->exists()) {
            return;
        }

        $accountId = fn (string $code) => ChartOfAccount::query()->where('account_code', $code)->value('id');
        $partyId = fn (string $code) => Party::query()->where('party_code', $code)->value('id');

        app(OpeningBalanceService::class)->save([
            'financial_year_id' => $financialYear->id,
            'balance_date' => '2026-01-01',
            'branch_location' => null,
            'status' => 'Final',
            'items' => [
                ['account_id' => $accountId('1010'), 'party_id' => null, 'debit_opening' => 50000, 'credit_opening' => 0, 'remarks' => 'Opening cash balance'],
                ['account_id' => $accountId('1020'), 'party_id' => null, 'debit_opening' => 100000, 'credit_opening' => 0, 'remarks' => 'Opening bank balance'],
                ['account_id' => $accountId('1100'), 'party_id' => $partyId('CUS-QA-001'), 'debit_opening' => 20000, 'credit_opening' => 0, 'remarks' => 'Opening customer receivable'],
                ['account_id' => $accountId('2010'), 'party_id' => $partyId('SUP-QA-001'), 'debit_opening' => 0, 'credit_opening' => 15000, 'remarks' => 'Opening supplier payable'],
                ['account_id' => $accountId('3010'), 'party_id' => null, 'debit_opening' => 0, 'credit_opening' => 155000, 'remarks' => 'Opening owner capital'],
            ],
        ], $userId);
    }
}
