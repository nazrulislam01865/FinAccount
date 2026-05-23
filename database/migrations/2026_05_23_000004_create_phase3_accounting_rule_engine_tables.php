<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->createAccountingRulesTable();
        $this->createAccountingRuleLinesTable();
        $this->backfillFromLegacyLedgerMappingRules();
    }

    public function down(): void
    {
        Schema::dropIfExists('accounting_rule_lines');
        Schema::dropIfExists('accounting_rules');
    }

    private function createAccountingRulesTable(): void
    {
        if (Schema::hasTable('accounting_rules')) {
            return;
        }

        Schema::create('accounting_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('legacy_ledger_mapping_rule_id')
                ->nullable()
                ->constrained('ledger_mapping_rules')
                ->nullOnDelete();
            $table->string('rule_code', 30);
            $table->string('rule_name', 150);
            $table->foreignId('transaction_head_id')->constrained()->restrictOnDelete();
            $table->foreignId('settlement_type_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('transaction_screen', 100)->nullable();
            $table->string('rule_trigger', 80)->default('Transaction Head selected');
            $table->boolean('amount_required')->default(true);
            $table->string('party_required_mode', 20)->default('No');
            $table->foreignId('party_type_id')->nullable()->constrained()->nullOnDelete();
            $table->string('party_sub_ledger_type', 50)->nullable();
            $table->boolean('payment_method_required')->default(false);
            $table->json('allowed_payment_methods')->nullable();
            $table->boolean('cash_bank_ledger_required')->default(false);
            $table->string('party_ledger_effect', 100)->default('No Effect');
            $table->boolean('auto_post')->default(true);
            $table->text('description')->nullable();
            $table->string('status', 30)->default('Draft');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['company_id', 'rule_code'], 'accounting_rules_company_code_unique');
            $table->index(
                ['company_id', 'transaction_head_id', 'settlement_type_id', 'status'],
                'accounting_rule_lookup_idx'
            );
            $table->index(['legacy_ledger_mapping_rule_id'], 'accounting_rules_legacy_idx');
        });
    }

    private function createAccountingRuleLinesTable(): void
    {
        if (Schema::hasTable('accounting_rule_lines')) {
            return;
        }

        Schema::create('accounting_rule_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('accounting_rule_id')->constrained()->cascadeOnDelete();
            $table->string('line_role', 30);
            $table->string('ledger_source', 50);
            $table->foreignId('ledger_id')
                ->nullable()
                ->constrained('chart_of_accounts')
                ->restrictOnDelete();
            $table->string('side', 10);
            $table->string('movement', 20)->nullable();
            $table->string('selection_method', 80)->nullable();
            $table->string('allowed_ledger_type', 80)->nullable();
            $table->string('amount_source', 50)->default('transaction_amount');
            $table->string('amount_formula')->nullable();
            $table->text('explanation')->nullable();
            $table->unsignedInteger('sort_order')->default(1);
            $table->timestamps();

            $table->index(['accounting_rule_id', 'sort_order'], 'accounting_rule_lines_order_idx');
            $table->index(['ledger_source', 'side'], 'accounting_rule_lines_source_side_idx');
        });
    }

    private function backfillFromLegacyLedgerMappingRules(): void
    {
        if (! Schema::hasTable('ledger_mapping_rules') || ! Schema::hasTable('accounting_rules')) {
            return;
        }

        $defaultCompanyId = DB::table('companies')->orderBy('id')->value('id');

        if (! $defaultCompanyId) {
            return;
        }

        DB::table('ledger_mapping_rules')
            ->whereNull('deleted_at')
            ->orderBy('id')
            ->select([
                'id',
                'company_id',
                'rule_code',
                'rule_name',
                'transaction_head_id',
                'settlement_type_id',
                'transaction_screen',
                'rule_trigger',
                'amount_required',
                'payment_method_required',
                'allowed_payment_method',
                'cash_bank_ledger_required',
                'party_required_mode',
                'party_sub_ledger_type',
                'primary_ledger_source',
                'primary_ledger_id',
                'primary_ledger_movement',
                'primary_posting_side',
                'primary_explanation',
                'counter_ledger_source',
                'counter_selection_method',
                'fixed_counter_ledger_id',
                'allowed_counter_ledger_type',
                'counter_ledger_movement',
                'counter_posting_side',
                'counter_explanation',
                'debit_account_id',
                'credit_account_id',
                'party_ledger_effect',
                'auto_post',
                'description',
                'status',
                'created_by',
                'updated_by',
                'created_at',
                'updated_at',
            ])
            ->chunkById(100, function ($legacyRules) use ($defaultCompanyId): void {
                foreach ($legacyRules as $legacy) {
                    $companyId = $legacy->company_id ?: $defaultCompanyId;
                    $ruleCode = $legacy->rule_code ?: ('AR-' . str_pad((string) $legacy->id, 3, '0', STR_PAD_LEFT));
                    $partyTypeId = $this->partyTypeIdFromName($legacy->party_sub_ledger_type ?? null);

                    $existingRule = DB::table('accounting_rules')
                        ->where('company_id', $companyId)
                        ->where('rule_code', $ruleCode)
                        ->first();

                    $payload = [
                        'company_id' => $companyId,
                        'legacy_ledger_mapping_rule_id' => $legacy->id,
                        'rule_code' => $ruleCode,
                        'rule_name' => $legacy->rule_name ?: ('Accounting Rule ' . $legacy->id),
                        'transaction_head_id' => $legacy->transaction_head_id,
                        'settlement_type_id' => $legacy->settlement_type_id,
                        'transaction_screen' => $legacy->transaction_screen,
                        'rule_trigger' => $legacy->rule_trigger ?: 'Transaction Head selected',
                        'amount_required' => (bool) ($legacy->amount_required ?? true),
                        'party_required_mode' => $legacy->party_required_mode ?: 'No',
                        'party_type_id' => $partyTypeId,
                        'party_sub_ledger_type' => $legacy->party_sub_ledger_type,
                        'payment_method_required' => (bool) ($legacy->payment_method_required ?? false),
                        'allowed_payment_methods' => json_encode($this->paymentMethods($legacy->allowed_payment_method ?? null)),
                        'cash_bank_ledger_required' => (bool) ($legacy->cash_bank_ledger_required ?? false),
                        'party_ledger_effect' => $legacy->party_ledger_effect ?: 'No Effect',
                        'auto_post' => (bool) ($legacy->auto_post ?? true),
                        'description' => $legacy->description,
                        'status' => $legacy->status ?: 'Active',
                        'created_by' => $legacy->created_by,
                        'updated_by' => $legacy->updated_by,
                        'created_at' => $legacy->created_at ?: now(),
                        'updated_at' => now(),
                    ];

                    if ($existingRule) {
                        DB::table('accounting_rules')->where('id', $existingRule->id)->update($payload);
                        $ruleId = $existingRule->id;
                    } else {
                        $ruleId = DB::table('accounting_rules')->insertGetId($payload);
                    }

                    DB::table('accounting_rule_lines')->where('accounting_rule_id', $ruleId)->delete();

                    DB::table('accounting_rule_lines')->insert([
                        $this->linePayload($ruleId, $legacy, 'primary', 1),
                        $this->linePayload($ruleId, $legacy, 'counter', 2),
                    ]);
                }
            }, 'id');
    }

    private function linePayload(int $ruleId, object $legacy, string $role, int $sortOrder): array
    {
        $isPrimary = $role === 'primary';
        $side = $isPrimary
            ? ($legacy->primary_posting_side ?: 'Debit')
            : ($legacy->counter_posting_side ?: 'Credit');

        $ledgerId = $isPrimary
            ? ($legacy->primary_ledger_id ?: ($side === 'Debit' ? $legacy->debit_account_id : $legacy->credit_account_id))
            : ($legacy->fixed_counter_ledger_id ?: ($side === 'Debit' ? $legacy->debit_account_id : $legacy->credit_account_id));

        return [
            'accounting_rule_id' => $ruleId,
            'line_role' => $role,
            'ledger_source' => $this->ledgerSource($isPrimary ? $legacy->primary_ledger_source : $legacy->counter_ledger_source),
            'ledger_id' => $ledgerId,
            'side' => $side,
            'movement' => $isPrimary ? $legacy->primary_ledger_movement : $legacy->counter_ledger_movement,
            'selection_method' => $isPrimary ? null : $legacy->counter_selection_method,
            'allowed_ledger_type' => $isPrimary ? null : $legacy->allowed_counter_ledger_type,
            'amount_source' => 'transaction_amount',
            'amount_formula' => null,
            'explanation' => $isPrimary ? $legacy->primary_explanation : $legacy->counter_explanation,
            'sort_order' => $sortOrder,
            'created_at' => now(),
            'updated_at' => now(),
        ];
    }

    private function ledgerSource(?string $source): string
    {
        $source = strtolower(trim((string) $source));

        return match (true) {
            str_contains($source, 'cash') || str_contains($source, 'bank') || str_contains($source, 'payment method') => 'user_cash_bank',
            str_contains($source, 'party') => 'party_control',
            str_contains($source, 'transaction head') => 'transaction_head',
            str_contains($source, 'system') => 'system_derived',
            default => 'fixed',
        };
    }

    /**
     * @return array<int, string>
     */
    private function paymentMethods(?string $value): array
    {
        $value = trim((string) $value);

        if ($value === '' || strtoupper($value) === 'N/A') {
            return [];
        }

        return array_values(array_filter(array_map('trim', explode(',', $value))));
    }

    private function partyTypeIdFromName(?string $name): ?int
    {
        $name = trim((string) $name);

        if ($name === '' || strtolower($name) === 'none') {
            return null;
        }

        return DB::table('party_types')
            ->whereRaw('LOWER(name) = ?', [strtolower($name)])
            ->value('id');
    }
};
