<?php

namespace Database\Seeders;

use App\Models\AccountingOption;
use Illuminate\Database\Seeder;

class AccountingOptionSeeder extends Seeder
{
    public function run(): void
    {
        $options = [
            [AccountingOption::GROUP_ACCOUNT_TYPE, 'Asset', 'Asset', 10, ['default_normal_balance' => 'Debit']],
            [AccountingOption::GROUP_ACCOUNT_TYPE, 'Liability', 'Liability', 20, ['default_normal_balance' => 'Credit']],
            [AccountingOption::GROUP_ACCOUNT_TYPE, 'Income', 'Income', 30, ['default_normal_balance' => 'Credit']],
            [AccountingOption::GROUP_ACCOUNT_TYPE, 'Expense', 'Expense', 40, ['default_normal_balance' => 'Debit']],
            [AccountingOption::GROUP_ACCOUNT_TYPE, 'Equity', 'Equity', 50, ['default_normal_balance' => 'Credit']],

            [AccountingOption::GROUP_NORMAL_BALANCE, 'Debit', 'Debit', 10, null],
            [AccountingOption::GROUP_NORMAL_BALANCE, 'Credit', 'Credit', 20, null],

            [AccountingOption::GROUP_MONEY_ACCOUNT_KIND, 'Cash', 'Cash', 10, null],
            [AccountingOption::GROUP_MONEY_ACCOUNT_KIND, 'Bank', 'Bank', 20, null],
            [AccountingOption::GROUP_MONEY_ACCOUNT_KIND, 'Digital', 'Digital', 30, null],

            [AccountingOption::GROUP_PARTY_TYPE, 'Customer', 'Customer', 10, null],
            [AccountingOption::GROUP_PARTY_TYPE, 'Supplier', 'Supplier', 20, null],
            [AccountingOption::GROUP_PARTY_TYPE, 'Worker', 'Worker', 30, null],
            [AccountingOption::GROUP_PARTY_TYPE, 'Owner', 'Owner', 40, null],
            [AccountingOption::GROUP_PARTY_TYPE, 'Lender', 'Lender', 50, null],

            [AccountingOption::GROUP_RULE_PARTY_TYPE, 'Any', 'Any', 5, null],
            [AccountingOption::GROUP_RULE_PARTY_TYPE, 'Customer', 'Customer', 10, null],
            [AccountingOption::GROUP_RULE_PARTY_TYPE, 'Supplier', 'Supplier', 20, null],
            [AccountingOption::GROUP_RULE_PARTY_TYPE, 'Worker', 'Worker', 30, null],
            [AccountingOption::GROUP_RULE_PARTY_TYPE, 'Owner', 'Owner', 40, null],
            [AccountingOption::GROUP_RULE_PARTY_TYPE, 'Lender', 'Lender', 50, null],

            [AccountingOption::GROUP_TRANSACTION_CATEGORY, 'SALE', 'Sale', 10, ['voucher_prefix' => 'SAL', 'money_label' => 'Received In', 'party_type' => 'Customer', 'allowed_settlements' => ['CASH', 'CREDIT', 'PARTIAL']]],
            [AccountingOption::GROUP_TRANSACTION_CATEGORY, 'PURCHASE', 'Purchase', 20, ['voucher_prefix' => 'PUR', 'money_label' => 'Paid From', 'party_type' => 'Supplier', 'allowed_settlements' => ['CASH', 'CREDIT', 'PARTIAL']]],
            [AccountingOption::GROUP_TRANSACTION_CATEGORY, 'CUSTOMER_COLLECTION', 'Customer Collection', 30, ['voucher_prefix' => 'COL', 'money_label' => 'Received In', 'party_type' => 'Customer', 'allowed_settlements' => ['CASH']]],
            [AccountingOption::GROUP_TRANSACTION_CATEGORY, 'SUPPLIER_PAYMENT', 'Supplier Payment', 40, ['voucher_prefix' => 'SPY', 'money_label' => 'Paid From', 'party_type' => 'Supplier', 'allowed_settlements' => ['CASH']]],
            [AccountingOption::GROUP_TRANSACTION_CATEGORY, 'EXPENSE', 'Expense', 50, ['voucher_prefix' => 'EXP', 'money_label' => 'Paid From', 'party_type' => 'Any', 'allowed_settlements' => ['CASH', 'CREDIT', 'PARTIAL']]],
            [AccountingOption::GROUP_TRANSACTION_CATEGORY, 'OWNER_INVESTMENT', 'Owner Investment', 60, ['voucher_prefix' => 'OIN', 'money_label' => 'Received In', 'party_type' => 'Owner', 'allowed_settlements' => ['CASH']]],
            [AccountingOption::GROUP_TRANSACTION_CATEGORY, 'OWNER_WITHDRAWAL', 'Owner Withdrawal', 70, ['voucher_prefix' => 'OWD', 'money_label' => 'Paid From', 'party_type' => 'Owner', 'allowed_settlements' => ['CASH']]],
            [AccountingOption::GROUP_TRANSACTION_CATEGORY, 'LOAN_RECEIVED', 'Loan Received', 80, ['voucher_prefix' => 'LRV', 'money_label' => 'Received In', 'party_type' => 'Lender', 'allowed_settlements' => ['CASH']]],
            [AccountingOption::GROUP_TRANSACTION_CATEGORY, 'LOAN_REPAYMENT', 'Loan Repayment', 90, ['voucher_prefix' => 'LRP', 'money_label' => 'Paid From', 'party_type' => 'Lender', 'allowed_settlements' => ['CASH']]],
            [AccountingOption::GROUP_TRANSACTION_CATEGORY, 'LOAN_INTEREST_PAYMENT', 'Loan Interest Payment', 100, ['voucher_prefix' => 'LIP', 'money_label' => 'Paid From', 'party_type' => 'Lender', 'allowed_settlements' => ['CASH']]],
            [AccountingOption::GROUP_TRANSACTION_CATEGORY, 'ASSET_PURCHASE', 'Asset Purchase', 110, ['voucher_prefix' => 'AST', 'money_label' => 'Paid From', 'party_type' => 'Supplier', 'allowed_settlements' => ['CASH', 'CREDIT', 'PARTIAL']]],

            [AccountingOption::GROUP_SETTLEMENT_TYPE, 'CASH', 'Paid/received in full', 10, null],
            [AccountingOption::GROUP_SETTLEMENT_TYPE, 'CREDIT', 'Fully due', 20, null],
            [AccountingOption::GROUP_SETTLEMENT_TYPE, 'PARTIAL', 'Part paid, remaining due', 30, null],

            [AccountingOption::GROUP_ACCOUNTING_SOURCE, 'selected_money', 'Selected Money Account', 10, ['requires_money' => true, 'default_credit' => true]],
            [AccountingOption::GROUP_ACCOUNTING_SOURCE, 'head_account', 'Transaction Head COA', 20, ['default_debit' => true]],
            [AccountingOption::GROUP_ACCOUNTING_SOURCE, 'party_receivable', 'Party Receivable COA', 30, ['requires_party' => true]],
            [AccountingOption::GROUP_ACCOUNTING_SOURCE, 'party_payable', 'Party Payable COA', 40, ['requires_party' => true]],
        ];

        foreach ($options as [$group, $value, $label, $sortOrder, $metadata]) {
            AccountingOption::query()->updateOrCreate(
                ['option_group' => $group, 'value' => $value],
                [
                    'label' => $label,
                    'sort_order' => $sortOrder,
                    'metadata' => $metadata,
                    'is_active' => true,
                ],
            );
        }
    }
}
