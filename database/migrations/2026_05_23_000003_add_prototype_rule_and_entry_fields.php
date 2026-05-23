<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->alignTransactionHeads();
        $this->alignLedgerMappingRules();
    }

    public function down(): void
    {
        if (Schema::hasTable('ledger_mapping_rules')) {
            Schema::table('ledger_mapping_rules', function (Blueprint $table) {
                $this->dropForeignIfExists('ledger_mapping_rules', 'ledger_mapping_rules_primary_ledger_id_foreign');
                $this->dropForeignIfExists('ledger_mapping_rules', 'ledger_mapping_rules_fixed_counter_ledger_id_foreign');

                foreach ([
                    'counter_explanation',
                    'counter_posting_side',
                    'counter_ledger_movement',
                    'allowed_counter_ledger_type',
                    'fixed_counter_ledger_id',
                    'counter_selection_method',
                    'counter_ledger_source',
                    'primary_explanation',
                    'primary_posting_side',
                    'primary_ledger_movement',
                    'primary_ledger_id',
                    'primary_ledger_source',
                    'other_required_input',
                    'party_sub_ledger_type',
                    'party_required_mode',
                    'cash_bank_ledger_required',
                    'allowed_payment_method',
                    'payment_method_required',
                    'amount_required',
                    'rule_trigger',
                    'transaction_screen',
                    'rule_name',
                ] as $column) {
                    if (Schema::hasColumn('ledger_mapping_rules', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }

        if (Schema::hasTable('transaction_heads')) {
            Schema::table('transaction_heads', function (Blueprint $table) {
                foreach ([
                    'developer_note',
                    'help_text',
                    'linked_accounting_rule_code',
                    'sort_order',
                    'is_user_selectable',
                    'is_system_default',
                ] as $column) {
                    if (Schema::hasColumn('transaction_heads', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }
    }

    private function alignTransactionHeads(): void
    {
        if (! Schema::hasTable('transaction_heads')) {
            return;
        }

        if (DB::getDriverName() === 'mysql' && Schema::hasColumn('transaction_heads', 'nature')) {
            DB::statement("ALTER TABLE transaction_heads MODIFY nature VARCHAR(30) NOT NULL DEFAULT 'Payment'");
        }

        Schema::table('transaction_heads', function (Blueprint $table) {
            if (! Schema::hasColumn('transaction_heads', 'is_system_default')) {
                $table->boolean('is_system_default')->default(false)->after('transaction_screen');
            }

            if (! Schema::hasColumn('transaction_heads', 'is_user_selectable')) {
                $table->boolean('is_user_selectable')->default(true)->after('is_system_default');
            }

            if (! Schema::hasColumn('transaction_heads', 'sort_order')) {
                $table->unsignedInteger('sort_order')->nullable()->after('is_user_selectable');
            }

            if (! Schema::hasColumn('transaction_heads', 'linked_accounting_rule_code')) {
                $table->string('linked_accounting_rule_code', 30)->nullable()->after('sort_order');
            }

            if (! Schema::hasColumn('transaction_heads', 'help_text')) {
                $table->text('help_text')->nullable()->after('description');
            }

            if (! Schema::hasColumn('transaction_heads', 'developer_note')) {
                $table->text('developer_note')->nullable()->after('help_text');
            }
        });

        DB::table('transaction_heads')
            ->whereNull('is_user_selectable')
            ->update(['is_user_selectable' => true]);

        if (Schema::hasColumn('transaction_heads', 'sort_order')) {
            DB::table('transaction_heads')
                ->whereNull('sort_order')
                ->orderBy('id')
                ->select(['id'])
                ->chunkById(100, function ($heads): void {
                    foreach ($heads as $index => $head) {
                        DB::table('transaction_heads')
                            ->where('id', $head->id)
                            ->update(['sort_order' => ((int) $head->id) * 10 + $index]);
                    }
                });
        }
    }

    private function alignLedgerMappingRules(): void
    {
        if (! Schema::hasTable('ledger_mapping_rules')) {
            return;
        }

        if (DB::getDriverName() === 'mysql' && Schema::hasColumn('ledger_mapping_rules', 'status')) {
            DB::statement("ALTER TABLE ledger_mapping_rules MODIFY status VARCHAR(30) NOT NULL DEFAULT 'Active'");
        }

        Schema::table('ledger_mapping_rules', function (Blueprint $table) {
            if (! Schema::hasColumn('ledger_mapping_rules', 'rule_name')) {
                $table->string('rule_name', 150)->nullable()->after('rule_code');
            }

            if (! Schema::hasColumn('ledger_mapping_rules', 'transaction_screen')) {
                $table->string('transaction_screen', 100)->nullable()->after('settlement_type_id');
            }

            if (! Schema::hasColumn('ledger_mapping_rules', 'rule_trigger')) {
                $table->string('rule_trigger', 80)->default('Transaction Head selected')->after('transaction_screen');
            }

            if (! Schema::hasColumn('ledger_mapping_rules', 'amount_required')) {
                $table->boolean('amount_required')->default(true)->after('rule_trigger');
            }

            if (! Schema::hasColumn('ledger_mapping_rules', 'payment_method_required')) {
                $table->boolean('payment_method_required')->default(false)->after('amount_required');
            }

            if (! Schema::hasColumn('ledger_mapping_rules', 'allowed_payment_method')) {
                $table->string('allowed_payment_method', 30)->nullable()->after('payment_method_required');
            }

            if (! Schema::hasColumn('ledger_mapping_rules', 'cash_bank_ledger_required')) {
                $table->boolean('cash_bank_ledger_required')->default(false)->after('allowed_payment_method');
            }

            if (! Schema::hasColumn('ledger_mapping_rules', 'party_required_mode')) {
                $table->string('party_required_mode', 20)->default('No')->after('cash_bank_ledger_required');
            }

            if (! Schema::hasColumn('ledger_mapping_rules', 'party_sub_ledger_type')) {
                $table->string('party_sub_ledger_type', 50)->nullable()->after('party_required_mode');
            }

            if (! Schema::hasColumn('ledger_mapping_rules', 'other_required_input')) {
                $table->string('other_required_input', 255)->nullable()->after('party_sub_ledger_type');
            }

            if (! Schema::hasColumn('ledger_mapping_rules', 'primary_ledger_source')) {
                $table->string('primary_ledger_source', 80)->nullable()->after('other_required_input');
            }

            if (! Schema::hasColumn('ledger_mapping_rules', 'primary_ledger_id')) {
                $table->foreignId('primary_ledger_id')
                    ->nullable()
                    ->after('primary_ledger_source')
                    ->constrained('chart_of_accounts')
                    ->nullOnDelete();
            }

            if (! Schema::hasColumn('ledger_mapping_rules', 'primary_ledger_movement')) {
                $table->string('primary_ledger_movement', 20)->nullable()->after('primary_ledger_id');
            }

            if (! Schema::hasColumn('ledger_mapping_rules', 'primary_posting_side')) {
                $table->string('primary_posting_side', 10)->nullable()->after('primary_ledger_movement');
            }

            if (! Schema::hasColumn('ledger_mapping_rules', 'primary_explanation')) {
                $table->text('primary_explanation')->nullable()->after('primary_posting_side');
            }

            if (! Schema::hasColumn('ledger_mapping_rules', 'counter_ledger_source')) {
                $table->string('counter_ledger_source', 80)->nullable()->after('primary_explanation');
            }

            if (! Schema::hasColumn('ledger_mapping_rules', 'counter_selection_method')) {
                $table->string('counter_selection_method', 80)->nullable()->after('counter_ledger_source');
            }

            if (! Schema::hasColumn('ledger_mapping_rules', 'fixed_counter_ledger_id')) {
                $table->foreignId('fixed_counter_ledger_id')
                    ->nullable()
                    ->after('counter_selection_method')
                    ->constrained('chart_of_accounts')
                    ->nullOnDelete();
            }

            if (! Schema::hasColumn('ledger_mapping_rules', 'allowed_counter_ledger_type')) {
                $table->string('allowed_counter_ledger_type', 80)->nullable()->after('fixed_counter_ledger_id');
            }

            if (! Schema::hasColumn('ledger_mapping_rules', 'counter_ledger_movement')) {
                $table->string('counter_ledger_movement', 20)->nullable()->after('allowed_counter_ledger_type');
            }

            if (! Schema::hasColumn('ledger_mapping_rules', 'counter_posting_side')) {
                $table->string('counter_posting_side', 10)->nullable()->after('counter_ledger_movement');
            }

            if (! Schema::hasColumn('ledger_mapping_rules', 'counter_explanation')) {
                $table->text('counter_explanation')->nullable()->after('counter_posting_side');
            }
        });

        DB::table('ledger_mapping_rules')
            ->whereNull('rule_name')
            ->orderBy('id')
            ->select([
                'id',
                'transaction_head_id',
                'settlement_type_id',
                'debit_account_id',
                'credit_account_id',
            ])
            ->chunkById(100, function ($rules): void {
                foreach ($rules as $rule) {
                    $head = DB::table('transaction_heads')->where('id', $rule->transaction_head_id)->first();
                    $settlement = DB::table('settlement_types')->where('id', $rule->settlement_type_id)->first();

                    DB::table('ledger_mapping_rules')
                        ->where('id', $rule->id)
                        ->update([
                            'rule_name' => trim(($head->name ?? 'Rule') . ' - ' . ($settlement->name ?? 'Settlement')),
                            'transaction_screen' => $head->transaction_screen ?? null,
                            'rule_trigger' => 'Transaction Head selected',
                            'amount_required' => true,
                            'payment_method_required' => (bool) ($head->payment_method_required ?? false),
                            'allowed_payment_method' => 'Cash, Bank',
                            'cash_bank_ledger_required' => true,
                            'party_required_mode' => (bool) ($head->requires_party ?? false) ? 'Yes' : 'No',
                            'primary_ledger_source' => 'Fixed Ledger',
                            'primary_ledger_id' => $rule->debit_account_id,
                            'primary_ledger_movement' => 'Increase',
                            'primary_posting_side' => 'Debit',
                            'counter_ledger_source' => 'Fixed Ledger',
                            'counter_selection_method' => 'Fixed by Rule',
                            'fixed_counter_ledger_id' => $rule->credit_account_id,
                            'counter_ledger_movement' => 'Decrease',
                            'counter_posting_side' => 'Credit',
                        ]);
                }
            });
    }

    private function dropForeignIfExists(string $table, string $foreign): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        $exists = DB::table('information_schema.table_constraints')
            ->whereRaw('table_schema = DATABASE()')
            ->where('table_name', $table)
            ->where('constraint_name', $foreign)
            ->where('constraint_type', 'FOREIGN KEY')
            ->exists();

        if ($exists) {
            Schema::table($table, fn (Blueprint $blueprint) => $blueprint->dropForeign($foreign));
        }
    }
};
