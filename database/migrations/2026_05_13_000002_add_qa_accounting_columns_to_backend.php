<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            if (!Schema::hasColumn('companies', 'default_branch')) {
                $table->string('default_branch')->nullable()->after('financial_year_end');
            }
        });

        Schema::table('cash_bank_accounts', function (Blueprint $table) {
            if (!Schema::hasColumn('cash_bank_accounts', 'cash_bank_code')) {
                $table->string('cash_bank_code', 30)->nullable()->after('company_id');
            }

            if (!Schema::hasColumn('cash_bank_accounts', 'usage_note')) {
                $table->string('usage_note')->nullable()->after('opening_balance');
            }
        });

        Schema::table('parties', function (Blueprint $table) {
            if (!Schema::hasColumn('parties', 'sub_type')) {
                $table->string('sub_type')->nullable()->after('party_type_id');
            }

            if (!Schema::hasColumn('parties', 'default_ledger_nature')) {
                $table->string('default_ledger_nature', 50)->nullable()->after('linked_ledger_account_id');
            }
        });

        Schema::table('chart_of_accounts', function (Blueprint $table) {
            if (!Schema::hasColumn('chart_of_accounts', 'account_level')) {
                $table->string('account_level', 20)->default('Ledger')->after('account_name');
            }

            if (!Schema::hasColumn('chart_of_accounts', 'normal_balance')) {
                $table->string('normal_balance', 20)->nullable()->after('account_type_id');
            }

            if (!Schema::hasColumn('chart_of_accounts', 'posting_allowed')) {
                $table->boolean('posting_allowed')->default(true)->after('is_cash_bank');
            }
        });

        Schema::table('transaction_heads', function (Blueprint $table) {
            if (!Schema::hasColumn('transaction_heads', 'head_code')) {
                $table->string('head_code', 30)->nullable()->after('company_id');
            }
        });

        Schema::table('ledger_mapping_rules', function (Blueprint $table) {
            if (!Schema::hasColumn('ledger_mapping_rules', 'rule_code')) {
                $table->string('rule_code', 30)->nullable()->after('company_id');
            }
        });

        Schema::table('opening_balances', function (Blueprint $table) {
            if (!Schema::hasColumn('opening_balances', 'balance_date')) {
                $table->date('balance_date')->nullable()->after('financial_year_id');
            }
        });

        $this->backfillChartOfAccounts();
        $this->backfillCashBankCodes();
        $this->backfillTransactionHeadCodes();
        $this->backfillLedgerMappingCodes();
        $this->backfillOpeningBalanceDates();

        Schema::table('cash_bank_accounts', function (Blueprint $table) {
            if (Schema::hasColumn('cash_bank_accounts', 'cash_bank_code')) {
                $table->unique('cash_bank_code', 'cash_bank_code_unique');
            }
        });

        Schema::table('transaction_heads', function (Blueprint $table) {
            if (Schema::hasColumn('transaction_heads', 'head_code')) {
                $table->unique('head_code', 'transaction_heads_head_code_unique');
            }
        });

        Schema::table('ledger_mapping_rules', function (Blueprint $table) {
            if (Schema::hasColumn('ledger_mapping_rules', 'rule_code')) {
                $table->unique('rule_code', 'ledger_mapping_rules_rule_code_unique');
            }
        });
    }

    public function down(): void
    {
        Schema::table('ledger_mapping_rules', function (Blueprint $table) {
            if (Schema::hasColumn('ledger_mapping_rules', 'rule_code')) {
                $table->dropUnique('ledger_mapping_rules_rule_code_unique');
                $table->dropColumn('rule_code');
            }
        });

        Schema::table('transaction_heads', function (Blueprint $table) {
            if (Schema::hasColumn('transaction_heads', 'head_code')) {
                $table->dropUnique('transaction_heads_head_code_unique');
                $table->dropColumn('head_code');
            }
        });

        Schema::table('opening_balances', function (Blueprint $table) {
            if (Schema::hasColumn('opening_balances', 'balance_date')) {
                $table->dropColumn('balance_date');
            }
        });

        Schema::table('chart_of_accounts', function (Blueprint $table) {
            if (Schema::hasColumn('chart_of_accounts', 'posting_allowed')) {
                $table->dropColumn('posting_allowed');
            }

            if (Schema::hasColumn('chart_of_accounts', 'normal_balance')) {
                $table->dropColumn('normal_balance');
            }

            if (Schema::hasColumn('chart_of_accounts', 'account_level')) {
                $table->dropColumn('account_level');
            }
        });

        Schema::table('parties', function (Blueprint $table) {
            if (Schema::hasColumn('parties', 'default_ledger_nature')) {
                $table->dropColumn('default_ledger_nature');
            }

            if (Schema::hasColumn('parties', 'sub_type')) {
                $table->dropColumn('sub_type');
            }
        });

        Schema::table('cash_bank_accounts', function (Blueprint $table) {
            if (Schema::hasColumn('cash_bank_accounts', 'cash_bank_code')) {
                $table->dropUnique('cash_bank_code_unique');
                $table->dropColumn('cash_bank_code');
            }

            if (Schema::hasColumn('cash_bank_accounts', 'usage_note')) {
                $table->dropColumn('usage_note');
            }
        });

        Schema::table('companies', function (Blueprint $table) {
            if (Schema::hasColumn('companies', 'default_branch')) {
                $table->dropColumn('default_branch');
            }
        });
    }

    private function backfillChartOfAccounts(): void
    {
        if (!Schema::hasTable('chart_of_accounts') || !Schema::hasTable('account_types')) {
            return;
        }

        $normalBalances = DB::table('account_types')->pluck('normal_balance', 'id');
        $parentIds = DB::table('chart_of_accounts')
            ->whereNotNull('parent_id')
            ->pluck('parent_id')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->all();

        DB::table('chart_of_accounts')
            ->orderBy('id')
            ->get(['id', 'account_type_id'])
            ->each(function ($account) use ($normalBalances, $parentIds) {
                $isGroup = in_array((int) $account->id, $parentIds, true);

                DB::table('chart_of_accounts')
                    ->where('id', $account->id)
                    ->update([
                        'account_level' => $isGroup ? 'Group' : 'Ledger',
                        'normal_balance' => $normalBalances[$account->account_type_id] ?? 'Debit',
                        'posting_allowed' => !$isGroup,
                    ]);
            });
    }

    private function backfillCashBankCodes(): void
    {
        if (!Schema::hasTable('cash_bank_accounts') || !Schema::hasColumn('cash_bank_accounts', 'cash_bank_code')) {
            return;
        }

        $sequence = ['Cash' => 0, 'Bank' => 0, 'Mobile Banking' => 0];

        DB::table('cash_bank_accounts')
            ->orderBy('id')
            ->get(['id', 'type', 'cash_bank_code'])
            ->each(function ($account) use (&$sequence) {
                if ($account->cash_bank_code) {
                    return;
                }

                $type = $account->type ?: 'Cash';
                $sequence[$type] = ($sequence[$type] ?? 0) + 1;
                $prefix = match ($type) {
                    'Bank' => 'BK',
                    'Mobile Banking' => 'MB',
                    default => 'CB',
                };

                DB::table('cash_bank_accounts')
                    ->where('id', $account->id)
                    ->update(['cash_bank_code' => $prefix . '-' . str_pad((string) $sequence[$type], 3, '0', STR_PAD_LEFT)]);
            });
    }

    private function backfillTransactionHeadCodes(): void
    {
        if (!Schema::hasTable('transaction_heads') || !Schema::hasColumn('transaction_heads', 'head_code')) {
            return;
        }

        $counter = 1;

        DB::table('transaction_heads')
            ->orderBy('id')
            ->get(['id', 'head_code'])
            ->each(function ($head) use (&$counter) {
                if (!$head->head_code) {
                    DB::table('transaction_heads')
                        ->where('id', $head->id)
                        ->update(['head_code' => 'TH-' . str_pad((string) $counter, 3, '0', STR_PAD_LEFT)]);
                }

                $counter++;
            });
    }

    private function backfillLedgerMappingCodes(): void
    {
        if (!Schema::hasTable('ledger_mapping_rules') || !Schema::hasColumn('ledger_mapping_rules', 'rule_code')) {
            return;
        }

        $counter = 1;

        DB::table('ledger_mapping_rules')
            ->orderBy('id')
            ->get(['id', 'rule_code'])
            ->each(function ($rule) use (&$counter) {
                if (!$rule->rule_code) {
                    DB::table('ledger_mapping_rules')
                        ->where('id', $rule->id)
                        ->update(['rule_code' => 'LM-' . str_pad((string) $counter, 3, '0', STR_PAD_LEFT)]);
                }

                $counter++;
            });
    }

    private function backfillOpeningBalanceDates(): void
    {
        if (!Schema::hasTable('opening_balances') || !Schema::hasColumn('opening_balances', 'balance_date')) {
            return;
        }

        $starts = DB::table('financial_years')->pluck('start_date', 'id');

        DB::table('opening_balances')
            ->whereNull('balance_date')
            ->orderBy('id')
            ->get(['id', 'financial_year_id'])
            ->each(function ($balance) use ($starts) {
                DB::table('opening_balances')
                    ->where('id', $balance->id)
                    ->update(['balance_date' => $starts[$balance->financial_year_id] ?? now()->toDateString()]);
            });
    }
};
