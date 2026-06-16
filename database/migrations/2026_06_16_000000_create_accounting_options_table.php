<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('accounting_options', function (Blueprint $table) {
            $table->id();
            $table->string('option_group', 60);
            $table->string('value', 60);
            $table->string('label', 120);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->json('metadata')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['option_group', 'value'], 'accounting_option_group_value_unique');
            $table->index(['option_group', 'is_active', 'sort_order'], 'accounting_option_lookup_index');
        });

        $now = now();
        $rows = collect($this->defaults())->map(fn (array $option): array => [
            'option_group' => $option[0],
            'value' => $option[1],
            'label' => $option[2],
            'sort_order' => $option[3],
            'metadata' => $option[4] === null ? null : json_encode($option[4], JSON_THROW_ON_ERROR),
            'is_active' => true,
            'created_at' => $now,
            'updated_at' => $now,
        ])->all();

        DB::table('accounting_options')->insert($rows);
    }

    public function down(): void
    {
        Schema::dropIfExists('accounting_options');
    }

    /** @return array<int, array{string, string, string, int, array<string, mixed>|null}> */
    private function defaults(): array
    {
        return [
            ['account_type', 'Asset', 'Asset', 10, ['default_normal_balance' => 'Debit']],
            ['account_type', 'Liability', 'Liability', 20, ['default_normal_balance' => 'Credit']],
            ['account_type', 'Income', 'Income', 30, ['default_normal_balance' => 'Credit']],
            ['account_type', 'Expense', 'Expense', 40, ['default_normal_balance' => 'Debit']],
            ['account_type', 'Equity', 'Equity', 50, ['default_normal_balance' => 'Credit']],
            ['normal_balance', 'Debit', 'Debit', 10, null],
            ['normal_balance', 'Credit', 'Credit', 20, null],
            ['money_account_kind', 'Cash', 'Cash', 10, null],
            ['money_account_kind', 'Bank', 'Bank', 20, null],
            ['money_account_kind', 'Digital', 'Digital', 30, null],
            ['party_type', 'Customer', 'Customer', 10, null],
            ['party_type', 'Supplier', 'Supplier', 20, null],
            ['party_type', 'Worker', 'Worker', 30, null],
            ['party_type', 'Owner', 'Owner', 40, null],
            ['party_type', 'Lender', 'Lender', 50, null],
            ['rule_party_type', 'Any', 'Any', 5, null],
            ['rule_party_type', 'Customer', 'Customer', 10, null],
            ['rule_party_type', 'Supplier', 'Supplier', 20, null],
            ['rule_party_type', 'Worker', 'Worker', 30, null],
            ['rule_party_type', 'Owner', 'Owner', 40, null],
            ['rule_party_type', 'Lender', 'Lender', 50, null],
            ['transaction_category', 'Sales', 'Sales', 10, ['voucher_prefix' => 'SAL', 'money_label' => 'Receive In']],
            ['transaction_category', 'Payment', 'Payment', 20, ['voucher_prefix' => 'PAY', 'money_label' => 'Pay/Receive Through']],
            ['transaction_category', 'Liability', 'Liability', 30, ['voucher_prefix' => 'LIA', 'money_label' => 'Pay/Receive Through']],
            ['accounting_source', 'selected_money', 'Selected Money Account', 10, ['requires_money' => true, 'default_credit' => true]],
            ['accounting_source', 'head_account', 'Transaction Head COA', 20, ['default_debit' => true]],
            ['accounting_source', 'party_receivable', 'Party Receivable COA', 30, ['requires_party' => true]],
            ['accounting_source', 'party_payable', 'Party Payable COA', 40, ['requires_party' => true]],
        ];
    }
};
