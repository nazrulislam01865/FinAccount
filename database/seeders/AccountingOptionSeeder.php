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

            [AccountingOption::GROUP_TRANSACTION_CATEGORY, 'Sales', 'Sales', 10, ['voucher_prefix' => 'SAL', 'money_label' => 'Receive In']],
            [AccountingOption::GROUP_TRANSACTION_CATEGORY, 'Payment', 'Payment', 20, ['voucher_prefix' => 'PAY', 'money_label' => 'Pay/Receive Through']],
            [AccountingOption::GROUP_TRANSACTION_CATEGORY, 'Liability', 'Liability', 30, ['voucher_prefix' => 'LIA', 'money_label' => 'Pay/Receive Through']],

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
