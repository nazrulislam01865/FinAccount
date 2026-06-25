<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** @var array<string, array<string, mixed>> */
    private array $types = [
        'SALE' => ['Sale', 'SAL', 'Received In', 'Customer', ['CASH', 'CREDIT', 'PARTIAL']],
        'PURCHASE' => ['Purchase', 'PUR', 'Paid From', 'Supplier', ['CASH', 'CREDIT', 'PARTIAL']],
        'CUSTOMER_COLLECTION' => ['Customer Collection', 'COL', 'Received In', 'Customer', ['CASH']],
        'SUPPLIER_PAYMENT' => ['Supplier Payment', 'SPY', 'Paid From', 'Supplier', ['CASH']],
        'EXPENSE' => ['Expense', 'EXP', 'Paid From', 'Any', ['CASH', 'CREDIT', 'PARTIAL']],
        'OWNER_INVESTMENT' => ['Owner Investment', 'OIN', 'Received In', 'Owner', ['CASH']],
        'OWNER_WITHDRAWAL' => ['Owner Withdrawal', 'OWD', 'Paid From', 'Owner', ['CASH']],
        'LOAN_RECEIVED' => ['Loan Received', 'LRV', 'Received In', 'Lender', ['CASH']],
        'LOAN_REPAYMENT' => ['Loan Repayment', 'LRP', 'Paid From', 'Lender', ['CASH']],
        'LOAN_INTEREST_PAYMENT' => ['Loan Interest Payment', 'LIP', 'Paid From', 'Lender', ['CASH']],
        'ASSET_PURCHASE' => ['Asset Purchase', 'AST', 'Paid From', 'Supplier', ['CASH', 'CREDIT', 'PARTIAL']],
    ];

    public function up(): void
    {
        Schema::table('accounting_rules', function (Blueprint $table): void {
            if (! Schema::hasColumn('accounting_rules', 'settlement_type')) {
                $table->string('settlement_type', 20)->nullable()->after('category');
                $table->index(
                    ['company_id', 'category', 'settlement_type', 'is_active'],
                    'rule_company_type_settlement_idx',
                );
            }
        });

        Schema::table('transaction_heads', function (Blueprint $table): void {
            if (! Schema::hasColumn('transaction_heads', 'allowed_settlements')) {
                $table->json('allowed_settlements')->nullable()->after('category');
            }

            if (! Schema::hasColumn('transaction_heads', 'party_type')) {
                $table->string('party_type', 30)->default('Any')->after('allowed_settlements');
            }
        });

        $this->seedOptions();
        $this->migrateRules();
        $this->migrateHeads();
        $this->migrateTransactions();
        $this->ensureRuleTemplates();
        $this->ensureVoucherSequences();
    }

    public function down(): void
    {
        DB::table('accounting_options')
            ->where('option_group', 'settlement_type')
            ->delete();

        DB::table('accounting_options')
            ->where('option_group', 'transaction_category')
            ->whereIn('value', array_keys($this->types))
            ->delete();

        foreach ([
            ['Sales', 'Sales', 10, 'SAL', 'Receive In'],
            ['Payment', 'Payment', 20, 'PAY', 'Pay/Receive Through'],
            ['Liability', 'Liability', 30, 'LIA', 'Pay/Receive Through'],
        ] as [$value, $label, $order, $prefix, $moneyLabel]) {
            DB::table('accounting_options')->updateOrInsert(
                ['option_group' => 'transaction_category', 'value' => $value],
                [
                    'label' => $label,
                    'sort_order' => $order,
                    'metadata' => json_encode(['voucher_prefix' => $prefix, 'money_label' => $moneyLabel]),
                    'is_active' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
            );
        }

        Schema::table('transaction_heads', function (Blueprint $table): void {
            if (Schema::hasColumn('transaction_heads', 'party_type')) {
                $table->dropColumn('party_type');
            }

            if (Schema::hasColumn('transaction_heads', 'allowed_settlements')) {
                $table->dropColumn('allowed_settlements');
            }
        });

        Schema::table('accounting_rules', function (Blueprint $table): void {
            if (Schema::hasColumn('accounting_rules', 'settlement_type')) {
                $table->dropIndex('rule_company_type_settlement_idx');
                $table->dropColumn('settlement_type');
            }
        });
    }

    private function seedOptions(): void
    {
        $now = now();
        $order = 10;

        foreach ($this->types as $value => [$label, $prefix, $moneyLabel, $partyType, $settlements]) {
            DB::table('accounting_options')->updateOrInsert(
                ['option_group' => 'transaction_category', 'value' => $value],
                [
                    'label' => $label,
                    'sort_order' => $order,
                    'metadata' => json_encode([
                        'voucher_prefix' => $prefix,
                        'money_label' => $moneyLabel,
                        'party_type' => $partyType,
                        'allowed_settlements' => $settlements,
                    ]),
                    'is_active' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
            );
            $order += 10;
        }

        foreach ([
            ['CASH', 'Paid/received in full', 10],
            ['CREDIT', 'Fully due', 20],
            ['PARTIAL', 'Part paid, remaining due', 30],
        ] as [$value, $label, $sortOrder]) {
            DB::table('accounting_options')->updateOrInsert(
                ['option_group' => 'settlement_type', 'value' => $value],
                [
                    'label' => $label,
                    'sort_order' => $sortOrder,
                    'metadata' => null,
                    'is_active' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
            );
        }

        DB::table('accounting_options')
            ->where('option_group', 'transaction_category')
            ->whereIn('value', ['Sales', 'Payment', 'Liability'])
            ->update(['is_active' => false, 'updated_at' => $now]);
    }

    private function migrateRules(): void
    {
        DB::table('accounting_rules')
            ->orderBy('id')
            ->chunkById(200, function ($rules): void {
                foreach ($rules as $rule) {
                    [$type, $settlement] = $this->classifyRule($rule);

                    DB::table('accounting_rules')
                        ->where('id', $rule->id)
                        ->update([
                            'category' => $type,
                            'settlement_type' => $settlement,
                            'generates_invoice' => $type === 'SALE',
                            'invoice_title' => $type === 'SALE' ? ($rule->invoice_title ?: 'Sales Invoice') : null,
                            'updated_at' => now(),
                        ]);
                }
            });
    }

    /** @return array{string,string} */
    private function classifyRule(object $rule): array
    {
        $haystack = strtoupper(trim(($rule->code ?? '').' '.($rule->name ?? '').' '.($rule->category ?? '')));

        $type = match (true) {
            str_contains($haystack, 'CUSTOMER') && str_contains($haystack, 'COLLECT') => 'CUSTOMER_COLLECTION',
            str_contains($haystack, 'SUPPLIER') && str_contains($haystack, 'PAY') => 'SUPPLIER_PAYMENT',
            str_contains($haystack, 'LOAN') && str_contains($haystack, 'INTEREST') => 'LOAN_INTEREST_PAYMENT',
            str_contains($haystack, 'LOAN') && (str_contains($haystack, 'REPAY') || str_contains($haystack, 'PRINCIPAL')) => 'LOAN_REPAYMENT',
            str_contains($haystack, 'LOAN') && str_contains($haystack, 'RECEIV') => 'LOAN_RECEIVED',
            str_contains($haystack, 'OWNER') && (str_contains($haystack, 'WITHDRAW') || str_contains($haystack, 'DRAW')) => 'OWNER_WITHDRAWAL',
            str_contains($haystack, 'OWNER') && (str_contains($haystack, 'INVEST') || str_contains($haystack, 'CAPITAL')) => 'OWNER_INVESTMENT',
            str_contains($haystack, 'ASSET') && str_contains($haystack, 'PURCHASE') => 'ASSET_PURCHASE',
            str_contains($haystack, 'SALE') || str_contains($haystack, 'SALES') => 'SALE',
            str_contains($haystack, 'PURCHASE') || str_contains($haystack, 'LIABILITY') => 'PURCHASE',
            default => 'EXPENSE',
        };

        $hasSplitLines = Schema::hasTable('accounting_rule_lines')
            && DB::table('accounting_rule_lines')
                ->where('accounting_rule_id', $rule->id)
                ->whereIn('amount_basis', ['paid', 'due'])
                ->exists();

        $settlement = match (true) {
            $hasSplitLines || str_contains($haystack, 'PARTIAL') => 'PARTIAL',
            str_contains($haystack, 'CREDIT') || str_contains($haystack, 'DUE') && ! str_contains($haystack, 'PAYMENT') => 'CREDIT',
            default => 'CASH',
        };

        if (in_array($type, [
            'CUSTOMER_COLLECTION', 'SUPPLIER_PAYMENT', 'OWNER_INVESTMENT', 'OWNER_WITHDRAWAL',
            'LOAN_RECEIVED', 'LOAN_REPAYMENT', 'LOAN_INTEREST_PAYMENT',
        ], true)) {
            $settlement = 'CASH';
        }

        return [$type, $settlement];
    }

    private function migrateHeads(): void
    {
        DB::table('transaction_heads')
            ->orderBy('id')
            ->chunkById(200, function ($heads): void {
                foreach ($heads as $head) {
                    $rule = $head->accounting_rule_id
                        ? DB::table('accounting_rules')->where('id', $head->accounting_rule_id)->first()
                        : null;
                    $type = $rule?->category ?: $this->classifyHead($head);
                    $definition = $this->types[$type] ?? $this->types['EXPENSE'];

                    DB::table('transaction_heads')
                        ->where('id', $head->id)
                        ->update([
                            'category' => $type,
                            'accounting_rule_id' => null,
                            'allowed_settlements' => json_encode($definition[4]),
                            'party_type' => $definition[3],
                            'updated_at' => now(),
                        ]);
                }
            });
    }

    private function classifyHead(object $head): string
    {
        $haystack = strtoupper(trim(($head->code ?? '').' '.($head->name ?? '').' '.($head->category ?? '')));

        return match (true) {
            str_contains($haystack, 'SUPPLIER') && str_contains($haystack, 'PAY') => 'SUPPLIER_PAYMENT',
            str_contains($haystack, 'COLLECT') => 'CUSTOMER_COLLECTION',
            str_contains($haystack, 'LOAN') && str_contains($haystack, 'INTEREST') => 'LOAN_INTEREST_PAYMENT',
            str_contains($haystack, 'LOAN') && (str_contains($haystack, 'REPAY') || str_contains($haystack, 'PRINCIPAL')) => 'LOAN_REPAYMENT',
            str_contains($haystack, 'LOAN') && str_contains($haystack, 'RECEIV') => 'LOAN_RECEIVED',
            str_contains($haystack, 'ASSET') || str_contains($haystack, 'MACHINE') || str_contains($haystack, 'VEHICLE') => 'ASSET_PURCHASE',
            str_contains($haystack, 'SALE') || str_contains($haystack, 'SALES') => 'SALE',
            str_contains($haystack, 'PURCHASE') || str_contains($haystack, 'LIABILITY') => 'PURCHASE',
            default => 'EXPENSE',
        };
    }

    private function migrateTransactions(): void
    {
        DB::table('transactions')
            ->orderBy('id')
            ->chunkById(200, function ($transactions): void {
                foreach ($transactions as $transaction) {
                    $head = DB::table('transaction_heads')->where('id', $transaction->transaction_head_id)->first();
                    $type = $head?->category ?: $this->legacyTransactionType((string) $transaction->category);
                    $oldSettlement = strtoupper((string) ($transaction->settlement_type ?? ''));

                    $settlement = match ($oldSettlement) {
                        'PARTIAL' => 'PARTIAL',
                        'CREDIT' => 'CREDIT',
                        'CASH' => 'CASH',
                        default => $transaction->money_account_id
                            ? 'CASH'
                            : ($transaction->party_id && in_array('CREDIT', $this->types[$type][4] ?? [], true) ? 'CREDIT' : 'CASH'),
                    };

                    $amount = (string) $transaction->amount;
                    $paid = match ($settlement) {
                        'CASH' => $amount,
                        'CREDIT' => '0.00',
                        default => $transaction->paid_amount,
                    };
                    $due = match ($settlement) {
                        'CASH' => '0.00',
                        'CREDIT' => $amount,
                        default => $transaction->due_amount,
                    };

                    DB::table('transactions')
                        ->where('id', $transaction->id)
                        ->update([
                            'category' => $type,
                            'settlement_type' => $settlement,
                            'paid_amount' => $paid,
                            'due_amount' => $due,
                            'updated_at' => now(),
                        ]);
                }
            });
    }

    private function legacyTransactionType(string $category): string
    {
        return match ($category) {
            'Sales' => 'SALE',
            'Liability' => 'PURCHASE',
            'Payment' => 'EXPENSE',
            default => array_key_exists($category, $this->types) ? $category : 'EXPENSE',
        };
    }

    private function ensureRuleTemplates(): void
    {
        if (! Schema::hasTable('companies') || ! Schema::hasTable('accounting_rule_lines')) {
            return;
        }

        foreach (DB::table('companies')->pluck('id') as $companyId) {
            foreach ($this->ruleTemplates() as $template) {
                $existing = DB::table('accounting_rules')
                    ->where('company_id', $companyId)
                    ->where('category', $template['type'])
                    ->where('settlement_type', $template['settlement'])
                    ->orderBy('id')
                    ->first();

                if ($existing) {
                    $ruleId = $existing->id;
                    DB::table('accounting_rules')
                        ->where('company_id', $companyId)
                        ->where('category', $template['type'])
                        ->where('settlement_type', $template['settlement'])
                        ->where('id', '!=', $ruleId)
                        ->update(['is_active' => false, 'updated_at' => now()]);

                    DB::table('accounting_rules')->where('id', $ruleId)->update([
                        'party_required' => $template['party_required'],
                        'party_type' => $template['party_type'],
                        'money_required' => $template['money_required'],
                        'generates_invoice' => $template['type'] === 'SALE',
                        'invoice_title' => $template['type'] === 'SALE' ? 'Sales Invoice' : null,
                        'is_active' => true,
                        'updated_at' => now(),
                    ]);
                } else {
                    $ruleId = DB::table('accounting_rules')->insertGetId([
                        'company_id' => $companyId,
                        'code' => $template['code'],
                        'name' => $template['name'],
                        'category' => $template['type'],
                        'settlement_type' => $template['settlement'],
                        'debit_source' => $template['lines'][0][1],
                        'credit_source' => collect($template['lines'])->first(fn (array $line): bool => $line[0] === 'credit')[1],
                        'party_required' => $template['party_required'],
                        'party_type' => $template['party_type'],
                        'money_required' => $template['money_required'],
                        'generates_invoice' => $template['type'] === 'SALE',
                        'invoice_title' => $template['type'] === 'SALE' ? 'Sales Invoice' : null,
                        'is_active' => true,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }

                DB::table('accounting_rule_lines')->where('accounting_rule_id', $ruleId)->delete();
                foreach ($template['lines'] as $index => [$side, $source, $basis]) {
                    DB::table('accounting_rule_lines')->insert([
                        'accounting_rule_id' => $ruleId,
                        'line_side' => $side,
                        'account_source' => $source,
                        'amount_basis' => $basis,
                        'sort_order' => $index + 1,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }
        }
    }

    /** @return array<int, array<string, mixed>> */
    private function ruleTemplates(): array
    {
        $templates = [];
        $add = function (
            string $type,
            string $settlement,
            string $code,
            string $name,
            string $partyType,
            bool $partyRequired,
            bool $moneyRequired,
            array $lines,
        ) use (&$templates): void {
            $templates[] = compact('type', 'settlement', 'code', 'name', 'partyType', 'partyRequired', 'moneyRequired', 'lines') + [
                'party_type' => $partyType,
                'party_required' => $partyRequired,
                'money_required' => $moneyRequired,
            ];
        };

        $add('SALE', 'CASH', 'SYS-SALE-CASH', 'Sale - Paid in Full', 'Any', false, true, [
            ['debit', 'selected_money', 'total'], ['credit', 'head_account', 'total'],
        ]);
        $add('SALE', 'CREDIT', 'SYS-SALE-CREDIT', 'Sale - Fully Due', 'Customer', true, false, [
            ['debit', 'party_receivable', 'total'], ['credit', 'head_account', 'total'],
        ]);
        $add('SALE', 'PARTIAL', 'SYS-SALE-PARTIAL', 'Sale - Part Received', 'Customer', true, true, [
            ['debit', 'selected_money', 'paid'], ['debit', 'party_receivable', 'due'], ['credit', 'head_account', 'total'],
        ]);

        foreach (['PURCHASE' => 'Purchase', 'EXPENSE' => 'Expense', 'ASSET_PURCHASE' => 'Asset Purchase'] as $type => $name) {
            $partyType = $type === 'EXPENSE' ? 'Any' : 'Supplier';
            $prefix = str_replace('_', '-', $type);
            $add($type, 'CASH', "SYS-{$prefix}-CASH", "{$name} - Paid in Full", 'Any', false, true, [
                ['debit', 'head_account', 'total'], ['credit', 'selected_money', 'total'],
            ]);
            $add($type, 'CREDIT', "SYS-{$prefix}-CREDIT", "{$name} - Fully Due", $partyType, true, false, [
                ['debit', 'head_account', 'total'], ['credit', 'party_payable', 'total'],
            ]);
            $add($type, 'PARTIAL', "SYS-{$prefix}-PARTIAL", "{$name} - Part Paid", $partyType, true, true, [
                ['debit', 'head_account', 'total'], ['credit', 'selected_money', 'paid'], ['credit', 'party_payable', 'due'],
            ]);
        }

        $add('CUSTOMER_COLLECTION', 'CASH', 'SYS-CUSTOMER-COLLECTION', 'Customer Due Collection', 'Customer', true, true, [
            ['debit', 'selected_money', 'total'], ['credit', 'party_receivable', 'total'],
        ]);
        $add('SUPPLIER_PAYMENT', 'CASH', 'SYS-SUPPLIER-PAYMENT', 'Supplier Due Payment', 'Supplier', true, true, [
            ['debit', 'party_payable', 'total'], ['credit', 'selected_money', 'total'],
        ]);
        $add('OWNER_INVESTMENT', 'CASH', 'SYS-OWNER-INVESTMENT', 'Owner Investment', 'Owner', true, true, [
            ['debit', 'selected_money', 'total'], ['credit', 'party_payable', 'total'],
        ]);
        $add('OWNER_WITHDRAWAL', 'CASH', 'SYS-OWNER-WITHDRAWAL', 'Owner Withdrawal', 'Owner', true, true, [
            ['debit', 'head_account', 'total'], ['credit', 'selected_money', 'total'],
        ]);
        $add('LOAN_RECEIVED', 'CASH', 'SYS-LOAN-RECEIVED', 'Loan Received', 'Lender', true, true, [
            ['debit', 'selected_money', 'total'], ['credit', 'party_payable', 'total'],
        ]);
        $add('LOAN_REPAYMENT', 'CASH', 'SYS-LOAN-REPAYMENT', 'Loan Principal Repayment', 'Lender', true, true, [
            ['debit', 'party_payable', 'total'], ['credit', 'selected_money', 'total'],
        ]);
        $add('LOAN_INTEREST_PAYMENT', 'CASH', 'SYS-LOAN-INTEREST', 'Loan Interest Payment', 'Lender', true, true, [
            ['debit', 'head_account', 'total'], ['credit', 'selected_money', 'total'],
        ]);

        return $templates;
    }

    private function ensureVoucherSequences(): void
    {
        if (! Schema::hasTable('document_sequences')) {
            return;
        }

        foreach (DB::table('companies')->pluck('id') as $companyId) {
            foreach ($this->types as $type => [$label, $prefix]) {
                $exists = DB::table('document_sequences')
                    ->where('company_id', $companyId)
                    ->where('category', $type)
                    ->exists();

                if ($exists) {
                    DB::table('document_sequences')
                        ->where('company_id', $companyId)
                        ->where('category', $type)
                        ->update(['prefix' => $prefix, 'updated_at' => now()]);
                } else {
                    DB::table('document_sequences')->insert([
                        'company_id' => $companyId,
                        'category' => $type,
                        'prefix' => $prefix,
                        'next_number' => 1,
                        'padding' => 4,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }
        }
    }
};
