<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('chart_of_accounts', function (Blueprint $table) {
            if (!Schema::hasColumn('chart_of_accounts', 'coa_level')) {
                $table->unsignedTinyInteger('coa_level')->nullable()->after('account_level');
            }

            if (!Schema::hasColumn('chart_of_accounts', 'account_group')) {
                $table->string('account_group', 100)->nullable()->after('account_type_id');
            }

            if (!Schema::hasColumn('chart_of_accounts', 'account_sub_group')) {
                $table->string('account_sub_group', 100)->nullable()->after('account_group');
            }

            if (!Schema::hasColumn('chart_of_accounts', 'account_nature')) {
                $table->string('account_nature', 50)->nullable()->after('account_sub_group');
            }

            if (!Schema::hasColumn('chart_of_accounts', 'ledger_type')) {
                $table->string('ledger_type', 50)->nullable()->after('posting_allowed');
            }

            if (!Schema::hasColumn('chart_of_accounts', 'is_party_control')) {
                $table->boolean('is_party_control')->default(false)->after('is_cash_bank');
            }

            if (!Schema::hasColumn('chart_of_accounts', 'party_type_id')) {
                $table->foreignId('party_type_id')->nullable()->after('is_party_control')->constrained('party_types')->nullOnDelete();
            }

            if (!Schema::hasColumn('chart_of_accounts', 'is_system_ledger')) {
                $table->boolean('is_system_ledger')->default(false)->after('party_type_id');
            }

            if (!Schema::hasColumn('chart_of_accounts', 'is_user_selectable')) {
                $table->boolean('is_user_selectable')->default(true)->after('is_system_ledger');
            }

            if (!Schema::hasColumn('chart_of_accounts', 'example_usage')) {
                $table->string('example_usage')->nullable()->after('description');
            }
        });

        $this->backfillSrsFields();
        $this->addIndexes();
    }

    public function down(): void
    {
        Schema::table('chart_of_accounts', function (Blueprint $table) {
            $this->dropIndexIfExists('chart_of_accounts', 'coa_company_level_status_idx');
            $this->dropIndexIfExists('chart_of_accounts', 'coa_company_ledger_status_idx');
            $this->dropIndexIfExists('chart_of_accounts', 'coa_company_party_control_idx');
            $this->dropIndexIfExists('chart_of_accounts', 'coa_parent_level_idx');
        });

        Schema::table('chart_of_accounts', function (Blueprint $table) {
            foreach ([
                'example_usage',
                'is_user_selectable',
                'is_system_ledger',
                'party_type_id',
                'is_party_control',
                'ledger_type',
                'account_nature',
                'account_sub_group',
                'account_group',
                'coa_level',
            ] as $column) {
                if (Schema::hasColumn('chart_of_accounts', $column)) {
                    if ($column === 'party_type_id') {
                        $this->dropForeignIfExists('chart_of_accounts', 'chart_of_accounts_party_type_id_foreign');
                    }
                    $table->dropColumn($column);
                }
            }
        });
    }

    private function backfillSrsFields(): void
    {
        if (!Schema::hasTable('chart_of_accounts')) {
            return;
        }

        $accounts = DB::table('chart_of_accounts')
            ->leftJoin('account_types', 'account_types.id', '=', 'chart_of_accounts.account_type_id')
            ->select(
                'chart_of_accounts.id',
                'chart_of_accounts.account_name',
                'chart_of_accounts.account_level',
                'chart_of_accounts.parent_id',
                'chart_of_accounts.is_cash_bank',
                'chart_of_accounts.posting_allowed',
                'chart_of_accounts.created_by',
                'account_types.name as account_type_name',
                'account_types.normal_balance as type_normal_balance'
            )
            ->orderBy('chart_of_accounts.id')
            ->get()
            ->keyBy('id');

        $partyTypes = Schema::hasTable('party_types')
            ? DB::table('party_types')->pluck('id', 'name')->mapWithKeys(fn ($id, $name) => [strtolower((string) $name) => $id])
            : collect();

        foreach ($accounts as $account) {
            $level = $this->resolveLevel((int) $account->id, $accounts);
            $accountType = (string) ($account->account_type_name ?: 'Asset');
            $ledgerType = $this->inferLedgerType($account, $level);
            $isPartyControl = $ledgerType === 'Party Control';
            $partyTypeId = $this->inferPartyTypeId((string) $account->account_name, $partyTypes);
            $classification = $this->classificationNames((int) $account->id, $accounts, $level);

            DB::table('chart_of_accounts')->where('id', $account->id)->update([
                'coa_level' => $level,
                'account_level' => $level === 4 ? 'Ledger' : 'Group',
                'account_group' => $classification['group'],
                'account_sub_group' => $classification['sub_group'],
                'account_nature' => $accountType,
                'normal_balance' => $account->type_normal_balance ?: null,
                'posting_allowed' => $level === 4,
                'ledger_type' => $level === 4 ? $ledgerType : 'Group',
                'is_cash_bank' => in_array($ledgerType, ['Cash', 'Bank'], true),
                'is_party_control' => $isPartyControl,
                'party_type_id' => $isPartyControl ? $partyTypeId : null,
                'is_system_ledger' => $account->created_by === null,
                'is_user_selectable' => $level === 4 && ! $isPartyControl,
                'updated_at' => now(),
            ]);
        }
    }

    private function resolveLevel(int $accountId, $accounts): int
    {
        $account = $accounts[$accountId] ?? null;

        if (! $account) {
            return 4;
        }

        if (($account->account_level ?? null) === 'Ledger') {
            return 4;
        }

        $depth = 1;
        $parentId = $account->parent_id;
        $guard = 0;

        while ($parentId && isset($accounts[$parentId]) && $guard < 10) {
            $depth++;
            $parentId = $accounts[$parentId]->parent_id;
            $guard++;
        }

        return min(max($depth, 1), 3);
    }

    private function classificationNames(int $accountId, $accounts, int $level): array
    {
        $account = $accounts[$accountId] ?? null;
        $ancestors = [];
        $parentId = $account?->parent_id;
        $guard = 0;

        while ($parentId && isset($accounts[$parentId]) && $guard < 10) {
            array_unshift($ancestors, $accounts[$parentId]);
            $parentId = $accounts[$parentId]->parent_id;
            $guard++;
        }

        $group = null;
        $subGroup = null;

        if ($level === 2) {
            $group = $account?->account_name;
        } elseif ($level === 3) {
            $group = $ancestors[1]->account_name ?? $ancestors[0]->account_name ?? null;
            $subGroup = $account?->account_name;
        } elseif ($level === 4) {
            $group = $ancestors[1]->account_name ?? $ancestors[0]->account_name ?? null;
            $subGroup = $ancestors[2]->account_name ?? $ancestors[1]->account_name ?? null;
        }

        return ['group' => $group, 'sub_group' => $subGroup];
    }

    private function inferLedgerType(object $account, int $level): string
    {
        if ($level !== 4) {
            return 'Group';
        }

        $name = strtolower((string) $account->account_name);

        if ((bool) $account->is_cash_bank) {
            return str_contains($name, 'bank') ? 'Bank' : 'Cash';
        }

        if (str_contains($name, 'receivable') || str_contains($name, 'payable') || str_contains($name, 'advance')) {
            return 'Party Control';
        }

        return match ((string) $account->account_type_name) {
            'Asset' => 'Asset',
            'Liability' => str_contains($name, 'loan') ? 'Loan' : 'Liability',
            'Equity', "Owner's Equity" => 'Equity',
            'Income' => 'Income',
            'Expense' => 'Expense',
            default => 'Asset',
        };
    }

    private function inferPartyTypeId(string $accountName, $partyTypes): ?int
    {
        $name = strtolower($accountName);

        foreach (['customer', 'supplier', 'employee', 'owner'] as $partyType) {
            if (str_contains($name, $partyType)) {
                return $partyTypes[$partyType] ?? null;
            }
        }

        return null;
    }

    private function addIndexes(): void
    {
        $this->addIndexIfMissing('chart_of_accounts', 'coa_company_level_status_idx', ['company_id', 'coa_level', 'status']);
        $this->addIndexIfMissing('chart_of_accounts', 'coa_company_ledger_status_idx', ['company_id', 'ledger_type', 'status']);
        $this->addIndexIfMissing('chart_of_accounts', 'coa_company_party_control_idx', ['company_id', 'is_party_control', 'party_type_id']);
        $this->addIndexIfMissing('chart_of_accounts', 'coa_parent_level_idx', ['parent_id', 'coa_level']);
    }

    private function addIndexIfMissing(string $table, string $index, array $columns): void
    {
        if (! Schema::hasTable($table) || $this->hasIndex($table, $index)) {
            return;
        }

        foreach ($columns as $column) {
            if (! Schema::hasColumn($table, $column)) {
                return;
            }
        }

        Schema::table($table, fn (Blueprint $blueprint) => $blueprint->index($columns, $index));
    }

    private function dropIndexIfExists(string $table, string $index): void
    {
        if (! Schema::hasTable($table) || ! $this->hasIndex($table, $index)) {
            return;
        }

        Schema::table($table, fn (Blueprint $blueprint) => $blueprint->dropIndex($index));
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

    private function hasIndex(string $table, string $index): bool
    {
        $driver = DB::getDriverName();

        if ($driver === 'mysql') {
            return DB::table('information_schema.statistics')
                ->whereRaw('table_schema = DATABASE()')
                ->where('table_name', $table)
                ->where('index_name', $index)
                ->exists();
        }

        if ($driver === 'sqlite') {
            foreach (DB::select('PRAGMA index_list(' . str_replace('"', '""', $table) . ')') as $row) {
                if (($row->name ?? null) === $index) {
                    return true;
                }
            }
        }

        if ($driver === 'pgsql') {
            return DB::table('pg_indexes')
                ->where('schemaname', 'public')
                ->where('tablename', $table)
                ->where('indexname', $index)
                ->exists();
        }

        return false;
    }
};
