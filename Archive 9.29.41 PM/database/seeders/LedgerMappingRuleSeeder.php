<?php

namespace Database\Seeders;

use App\Models\ChartOfAccount;
use App\Models\LedgerMappingRule;
use App\Models\SettlementType;
use App\Models\TransactionHead;
use Illuminate\Database\Seeder;

class LedgerMappingRuleSeeder extends Seeder
{
    public function run(): void
    {
        $head = fn (string $name) => TransactionHead::where('name', $name)->value('id');
        $settlement = fn (string $name) => SettlementType::where('name', $name)->value('id');
        $account = fn (string $name) => ChartOfAccount::where('account_name', $name)->value('id');

        $rules = [
            ['Salary Payment', 'Cash', 'Salary Expense', 'Cash', 'No Effect', 'Records salary paid from cash.'],
            ['Salary Payment', 'Bank', 'Salary Expense', 'Bank - Operating A/C', 'No Effect', 'Records salary paid from bank.'],

            ['Salary Due Entry', 'Due', 'Salary Expense', 'Salary Payable', 'Increase Liability', 'Records salary expense payable without cash/bank movement.'],
            ['Salary Due Payment', 'Cash', 'Salary Payable', 'Cash', 'Decrease Liability', 'Pays previous salary payable from cash without recording salary expense again.'],
            ['Salary Due Payment', 'Bank', 'Salary Payable', 'Bank - Operating A/C', 'Decrease Liability', 'Pays previous salary payable from bank without recording salary expense again.'],

            ['Fuel Expense', 'Cash', 'Fuel Expense', 'Cash', 'No Effect', 'Records immediate fuel expense paid by cash.'],
            ['Fuel Expense', 'Bank', 'Fuel Expense', 'Bank - Operating A/C', 'No Effect', 'Records immediate fuel expense paid by bank.'],
            ['Fuel Expense', 'Due', 'Fuel Expense', 'Accounts Payable', 'Increase Liability', 'Records fuel expense payable without cash/bank movement.'],

            ['Vehicle Maintenance', 'Cash', 'Vehicle Maintenance Expense', 'Cash', 'No Effect', 'Records immediate vehicle maintenance paid by cash.'],
            ['Vehicle Maintenance', 'Bank', 'Vehicle Maintenance Expense', 'Bank - Operating A/C', 'No Effect', 'Records immediate vehicle maintenance paid by bank.'],
            ['Vehicle Maintenance', 'Due', 'Vehicle Maintenance Expense', 'Accounts Payable', 'Increase Liability', 'Records vehicle maintenance payable without cash/bank movement.'],

            ['Rent Income', 'Cash', 'Cash', 'Rent Income', 'No Effect', 'Records rent income received in cash.'],
            ['Rent Income', 'Bank', 'Bank - Operating A/C', 'Rent Income', 'No Effect', 'Records rent income received in bank.'],
            ['Rent Income', 'Due', 'Accounts Receivable', 'Rent Income', 'Increase Receivable', 'Records rent receivable without cash/bank movement.'],

            ['Customer Payment Received', 'Cash', 'Cash', 'Accounts Receivable', 'Decrease Receivable', 'Collects previous receivable in cash without recording income again.'],
            ['Customer Payment Received', 'Bank', 'Bank - Operating A/C', 'Accounts Receivable', 'Decrease Receivable', 'Collects previous receivable by bank without recording income again.'],

            ['Supplier Payment', 'Cash', 'Accounts Payable', 'Cash', 'Decrease Liability', 'Pays previous supplier payable by cash without recording expense again.'],
            ['Supplier Payment', 'Bank', 'Accounts Payable', 'Bank - Operating A/C', 'Decrease Liability', 'Pays previous supplier payable by bank without recording expense again.'],

            ['Advance Paid', 'Cash', 'Advance to Supplier', 'Cash', 'Increase Advance Asset', 'Records advance paid by cash as an asset.'],
            ['Advance Paid', 'Bank', 'Advance to Supplier', 'Bank - Operating A/C', 'Increase Advance Asset', 'Records advance paid by bank as an asset.'],

            ['Advance Received', 'Cash', 'Cash', 'Advance from Customer', 'Increase Advance Liability', 'Records advance received in cash as a liability.'],
            ['Advance Received', 'Bank', 'Bank - Operating A/C', 'Advance from Customer', 'Increase Advance Liability', 'Records advance received in bank as a liability.'],
        ];

        foreach ($rules as [$headName, $settlementName, $debitName, $creditName, $effect, $description]) {
            $transactionHeadId = $head($headName);
            $settlementTypeId = $settlement($settlementName);
            $debitAccountId = $account($debitName);
            $creditAccountId = $account($creditName);

            if (!$transactionHeadId || !$settlementTypeId || !$debitAccountId || !$creditAccountId) {
                continue;
            }

            LedgerMappingRule::updateOrCreate(
                [
                    'transaction_head_id' => $transactionHeadId,
                    'settlement_type_id' => $settlementTypeId,
                ],
                [
                    'debit_account_id' => $debitAccountId,
                    'credit_account_id' => $creditAccountId,
                    'party_ledger_effect' => $effect,
                    'auto_post' => true,
                    'description' => $description,
                    'status' => 'Active',
                ]
            );
        }
    }
}
