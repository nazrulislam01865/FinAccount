<?php

namespace Database\Seeders;

use App\Models\ChartOfAccount;
use App\Models\Company;
use App\Models\LedgerMappingRule;
use App\Models\SettlementType;
use App\Models\TransactionHead;
use Illuminate\Database\Seeder;

class LedgerMappingRuleSeeder extends Seeder
{
    public function run(): void
    {
        $company = Company::query()->first();

        $account = fn (string $code) => ChartOfAccount::query()
            ->where('account_code', $code)
            ->where('status', 'Active')
            ->first();

        $head = fn (string $name) => TransactionHead::query()
            ->where('name', $name)
            ->where('status', 'Active')
            ->first();

        $settlement = fn (string $code) => SettlementType::query()
            ->where('code', $code)
            ->where('status', 'Active')
            ->first();

        $cash = $account('1010');
        $bank = $account('1020');
        $ar = $account('1100');
        $advanceAsset = $account('1200');
        $ap = $account('2010');
        $salaryPayable = $account('2020');
        $advanceLiability = $account('2030');
        $income = $account('4010');
        $serviceIncome = $account('4020');
        $salaryExpense = $account('5010');
        $fuelExpense = $account('5020');
        $rentExpense = $account('5040');

        $rules = [
            ['LM-001', 'Salary Payment', 'CASH', $salaryExpense, $cash, 'No Effect', 'Salary paid in cash: Dr Salary Expense / Cr Cash.'],
            ['LM-002', 'Salary Payment', 'BANK', $salaryExpense, $bank, 'No Effect', 'Salary paid by bank: Dr Salary Expense / Cr Bank.'],
            ['LM-003', 'Salary Due Entry', 'DUE', $salaryExpense, $salaryPayable, 'Increase Liability', 'Salary due: Dr Salary Expense / Cr Salary Payable.'],
            ['LM-004', 'Salary Due Payment', 'CASH', $salaryPayable, $cash, 'Decrease Liability', 'Salary due paid by cash: Dr Salary Payable / Cr Cash.'],
            ['LM-005', 'Salary Due Payment', 'BANK', $salaryPayable, $bank, 'Decrease Liability', 'Salary due paid by bank: Dr Salary Payable / Cr Bank.'],
            ['LM-006', 'Fuel Expense', 'CASH', $fuelExpense, $cash, 'No Effect', 'Fuel paid in cash: Dr Fuel Expense / Cr Cash.'],
            ['LM-007', 'Fuel Expense', 'BANK', $fuelExpense, $bank, 'No Effect', 'Fuel paid by bank: Dr Fuel Expense / Cr Bank.'],
            ['LM-008', 'Fuel Expense', 'DUE', $fuelExpense, $ap, 'Increase Liability', 'Fuel due: Dr Fuel Expense / Cr Accounts Payable.'],
            ['LM-009', 'Supplier Payment', 'CASH', $ap, $cash, 'Decrease Liability', 'Supplier due paid by cash: Dr Accounts Payable / Cr Cash.'],
            ['LM-010', 'Supplier Payment', 'BANK', $ap, $bank, 'Decrease Liability', 'Supplier due paid by bank: Dr Accounts Payable / Cr Bank.'],
            ['LM-011', 'Vehicle Rent Income', 'CASH', $cash, $income, 'No Effect', 'Rent received in cash: Dr Cash / Cr Rent Income.'],
            ['LM-012', 'Vehicle Rent Income', 'BANK', $bank, $income, 'No Effect', 'Rent received by bank: Dr Bank / Cr Rent Income.'],
            ['LM-013', 'Vehicle Rent Income', 'DUE', $ar, $income, 'Increase Receivable', 'Rent receivable: Dr Accounts Receivable / Cr Rent Income.'],
            ['LM-014', 'Customer Payment Received', 'CASH', $cash, $ar, 'Decrease Receivable', 'Customer due collected in cash: Dr Cash / Cr Accounts Receivable.'],
            ['LM-015', 'Customer Payment Received', 'BANK', $bank, $ar, 'Decrease Receivable', 'Customer due collected by bank: Dr Bank / Cr Accounts Receivable.'],
            ['LM-016', 'Advance Paid', 'CASH', $advanceAsset, $cash, 'Increase Advance Asset', 'Advance paid in cash: Dr Advance to Supplier / Cr Cash.'],
            ['LM-017', 'Advance Paid', 'BANK', $advanceAsset, $bank, 'Increase Advance Asset', 'Advance paid by bank: Dr Advance to Supplier / Cr Bank.'],
            ['LM-018', 'Advance Received', 'CASH', $cash, $advanceLiability, 'Increase Advance Liability', 'Advance received in cash: Dr Cash / Cr Advance from Customer.'],
            ['LM-019', 'Advance Received', 'BANK', $bank, $advanceLiability, 'Increase Advance Liability', 'Advance received by bank: Dr Bank / Cr Advance from Customer.'],
            ['LM-020', 'Office Rent Expense', 'CASH', $rentExpense, $cash, 'No Effect', 'Office rent paid in cash: Dr Office Rent Expense / Cr Cash.'],
            ['LM-021', 'Office Rent Expense', 'BANK', $rentExpense, $bank, 'No Effect', 'Office rent paid by bank: Dr Office Rent Expense / Cr Bank.'],
            ['LM-022', 'Office Rent Expense', 'DUE', $rentExpense, $ap, 'Increase Liability', 'Office rent due: Dr Office Rent Expense / Cr Accounts Payable.'],
        ];

        foreach ($rules as [$code, $headName, $settlementCode, $debit, $credit, $effect, $description]) {
            $transactionHead = $head($headName);
            $settlementType = $settlement($settlementCode);

            if (!$transactionHead || !$settlementType || !$debit || !$credit) {
                continue;
            }

            LedgerMappingRule::query()->updateOrCreate(
                [
                    'company_id' => $company?->id,
                    'transaction_head_id' => $transactionHead->id,
                    'settlement_type_id' => $settlementType->id,
                ],
                [
                    'rule_code' => $code,
                    'debit_account_id' => $debit->id,
                    'credit_account_id' => $credit->id,
                    'party_ledger_effect' => $effect,
                    'auto_post' => true,
                    'description' => $description,
                    'status' => 'Active',
                    'created_by' => 1,
                    'updated_by' => 1,
                ]
            );
        }
    }
}
