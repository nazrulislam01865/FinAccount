<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * These are setup/configuration links, not historical posting rows.
     * They must be nullable so a deleted CoA branch can leave the affected
     * setup record blank and ready for a replacement ledger assignment.
     */
    public function up(): void
    {
        $this->makeNullableCoaLink('cash_bank_accounts', 'linked_ledger_account_id');
        $this->makeNullableCoaLink('parties', 'linked_ledger_account_id');
        $this->makeNullableCoaLink('ledger_mapping_rules', 'debit_account_id');
        $this->makeNullableCoaLink('ledger_mapping_rules', 'credit_account_id');
    }

    public function down(): void
    {
        // Keep the columns nullable because rows may legitimately be waiting
        // for reassignment after a CoA deletion. Restore restrictive FK action
        // without risking data loss or an invalid rollback.
        $this->replaceForeignKey('cash_bank_accounts', 'linked_ledger_account_id', false);
        $this->replaceForeignKey('parties', 'linked_ledger_account_id', false);
        $this->replaceForeignKey('ledger_mapping_rules', 'debit_account_id', false);
        $this->replaceForeignKey('ledger_mapping_rules', 'credit_account_id', false);
    }

    private function makeNullableCoaLink(string $table, string $column): void
    {
        if (! Schema::hasTable($table) || ! Schema::hasColumn($table, $column)) {
            return;
        }

        $this->dropForeignIfExists($table, $column);

        Schema::table($table, function (Blueprint $blueprint) use ($column): void {
            $blueprint->unsignedBigInteger($column)->nullable()->change();
        });

        $this->addForeignKey($table, $column, true);
    }

    private function replaceForeignKey(string $table, string $column, bool $nullOnDelete): void
    {
        if (! Schema::hasTable($table) || ! Schema::hasColumn($table, $column)) {
            return;
        }

        $this->dropForeignIfExists($table, $column);
        $this->addForeignKey($table, $column, $nullOnDelete);
    }

    private function addForeignKey(string $table, string $column, bool $nullOnDelete): void
    {
        Schema::table($table, function (Blueprint $blueprint) use ($column, $nullOnDelete): void {
            $foreign = $blueprint->foreign($column)
                ->references('id')
                ->on('chart_of_accounts');

            if ($nullOnDelete) {
                $foreign->nullOnDelete();
            } else {
                $foreign->restrictOnDelete();
            }
        });
    }

    private function dropForeignIfExists(string $table, string $column): void
    {
        $expectedName = $table . '_' . $column . '_foreign';
        $foreignKeys = collect(Schema::getForeignKeys($table));

        $exists = $foreignKeys->contains(function (array $foreign) use ($expectedName, $column): bool {
            $name = (string) ($foreign['name'] ?? '');
            $columns = $foreign['columns'] ?? [];

            return $name === $expectedName || $columns === [$column];
        });

        if (! $exists) {
            return;
        }

        Schema::table($table, function (Blueprint $blueprint) use ($expectedName): void {
            $blueprint->dropForeign($expectedName);
        });
    }
};
