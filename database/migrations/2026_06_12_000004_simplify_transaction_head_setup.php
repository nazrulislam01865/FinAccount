<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('transaction_heads')) {
            return;
        }


        $dropColumns = collect(['linked_accounting_rule_code', 'developer_note', 'description'])
            ->filter(fn (string $column): bool => Schema::hasColumn('transaction_heads', $column))
            ->values()
            ->all();

        if ($dropColumns !== []) {
            Schema::table('transaction_heads', function (Blueprint $table) use ($dropColumns): void {
                $table->dropColumn($dropColumns);
            });
        }

        if (Schema::hasColumn('transaction_heads', 'head_code')) {
            DB::table('transaction_heads')
                ->whereNull('head_code')
                ->orWhere('head_code', '')
                ->orderBy('id')
                ->select(['id'])
                ->chunkById(100, function ($heads): void {
                    foreach ($heads as $head) {
                        DB::table('transaction_heads')
                            ->where('id', $head->id)
                            ->update([
                                'head_code' => 'TH-GEN-' . str_pad((string) $head->id, 4, '0', STR_PAD_LEFT),
                            ]);
                    }
                });

            // Earlier versions made Head Code globally unique. The setup is
            // company-scoped now, so separate companies may use the same code.
            try {
                Schema::table('transaction_heads', function (Blueprint $table): void {
                    $table->dropUnique('transaction_heads_head_code_unique');
                });
            } catch (\Throwable) {
                // The index may already have been removed in a previous deploy.
            }

            if (! $this->indexExists('transaction_heads', 'transaction_heads_company_head_code_unique')) {
                Schema::table('transaction_heads', function (Blueprint $table): void {
                    $table->unique(
                        ['company_id', 'head_code'],
                        'transaction_heads_company_head_code_unique'
                    );
                });
            }
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('transaction_heads') || ! Schema::hasColumn('transaction_heads', 'head_code')) {
            return;
        }

        $restoreDescription = ! Schema::hasColumn('transaction_heads', 'description');
        $restoreRuleCode = ! Schema::hasColumn('transaction_heads', 'linked_accounting_rule_code');
        $restoreDeveloperNote = ! Schema::hasColumn('transaction_heads', 'developer_note');

        if ($restoreDescription || $restoreRuleCode || $restoreDeveloperNote) {
            Schema::table('transaction_heads', function (Blueprint $table) use (
                $restoreDescription,
                $restoreRuleCode,
                $restoreDeveloperNote
            ): void {
                if ($restoreDescription) {
                    $table->text('description')->nullable();
                }
                if ($restoreRuleCode) {
                    $table->string('linked_accounting_rule_code', 30)->nullable();
                }
                if ($restoreDeveloperNote) {
                    $table->text('developer_note')->nullable();
                }
            });
        }

        try {
            Schema::table('transaction_heads', function (Blueprint $table): void {
                $table->dropUnique('transaction_heads_company_head_code_unique');
            });
        } catch (\Throwable) {
        }

        if (! $this->indexExists('transaction_heads', 'transaction_heads_head_code_unique')) {
            Schema::table('transaction_heads', function (Blueprint $table): void {
                $table->unique('head_code', 'transaction_heads_head_code_unique');
            });
        }
    }

    private function indexExists(string $table, string $index): bool
    {
        $driver = DB::getDriverName();

        if ($driver === 'mysql') {
            return DB::table('information_schema.statistics')
                ->where('table_schema', DB::getDatabaseName())
                ->where('table_name', $table)
                ->where('index_name', $index)
                ->exists();
        }

        if ($driver === 'sqlite') {
            return collect(DB::select("PRAGMA index_list('{$table}')"))
                ->contains(fn ($row) => ($row->name ?? null) === $index);
        }

        if ($driver === 'pgsql') {
            return DB::table('pg_indexes')
                ->where('tablename', $table)
                ->where('indexname', $index)
                ->exists();
        }

        return false;
    }
};
