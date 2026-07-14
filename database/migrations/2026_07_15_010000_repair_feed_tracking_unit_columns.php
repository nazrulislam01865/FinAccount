<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->normalizeColumn('transactions', 'warehouse_id', 'tracking_unit_id', true);
        $this->normalizeColumn('feed_documents', 'warehouse_id', 'tracking_unit_id', false);
        $this->normalizeColumn('feed_stock_balances', 'warehouse_id', 'tracking_unit_id', false);
        $this->normalizeColumn('feed_stock_movements', 'warehouse_id', 'tracking_unit_id', false);
        $this->normalizeColumn('feed_settings', 'default_warehouse_id', 'default_tracking_unit_id', true);
    }

    public function down(): void
    {
        // Intentionally not renamed back. The application now consistently uses tracking_unit_id.
    }

    private function normalizeColumn(string $tableName, string $oldColumn, string $newColumn, bool $nullable): void
    {
        if (! Schema::hasTable($tableName)) {
            return;
        }

        $hasOld = Schema::hasColumn($tableName, $oldColumn);
        $hasNew = Schema::hasColumn($tableName, $newColumn);

        if ($hasOld && ! $hasNew) {
            $this->dropForeignKeysForColumn($tableName, $oldColumn);
            Schema::table($tableName, function (Blueprint $table) use ($oldColumn, $newColumn): void {
                $table->renameColumn($oldColumn, $newColumn);
            });
            $this->ensureWarehouseForeignKey($tableName, $newColumn, $nullable);
            return;
        }

        if ($hasOld && $hasNew) {
            DB::statement(sprintf(
                'UPDATE `%s` SET `%s` = COALESCE(`%s`, `%s`)',
                str_replace('`', '``', $tableName),
                str_replace('`', '``', $newColumn),
                str_replace('`', '``', $newColumn),
                str_replace('`', '``', $oldColumn),
            ));

            $this->dropForeignKeysForColumn($tableName, $oldColumn);
            Schema::table($tableName, function (Blueprint $table) use ($oldColumn): void {
                $table->dropColumn($oldColumn);
            });
        }

        if (Schema::hasColumn($tableName, $newColumn)) {
            $this->ensureWarehouseForeignKey($tableName, $newColumn, $nullable);
        }
    }

    private function ensureWarehouseForeignKey(string $tableName, string $column, bool $nullable): void
    {
        if (! $nullable) {
            // Keep required stock/document references non-null on MySQL databases that were partially migrated.
            // Drop the FK first because MySQL will not always allow MODIFY on a constrained column.
            $columnInfo = $this->columnInfo($tableName, $column);
            if ($columnInfo && strtoupper((string) $columnInfo->IS_NULLABLE) === 'YES') {
                $this->dropForeignKeysForColumn($tableName, $column);
                DB::statement(sprintf(
                    'ALTER TABLE `%s` MODIFY `%s` BIGINT UNSIGNED NOT NULL',
                    str_replace('`', '``', $tableName),
                    str_replace('`', '``', $column),
                ));
            }
        }

        $foreignKeys = $this->foreignKeysForColumn($tableName, $column);
        $alreadyCorrect = collect($foreignKeys)->contains(
            fn ($key): bool => (string) $key->REFERENCED_TABLE_NAME === 'feed_warehouses'
        );

        if ($alreadyCorrect) {
            return;
        }

        $this->dropForeignKeysForColumn($tableName, $column);
        $constraint = $tableName.'_'.$column.'_foreign';

        DB::statement(sprintf(
            'ALTER TABLE `%s` ADD CONSTRAINT `%s` FOREIGN KEY (`%s`) REFERENCES `feed_warehouses` (`id`) ON DELETE RESTRICT',
            str_replace('`', '``', $tableName),
            str_replace('`', '``', $constraint),
            str_replace('`', '``', $column),
        ));
    }

    private function dropForeignKeysForColumn(string $tableName, string $column): void
    {
        foreach ($this->foreignKeysForColumn($tableName, $column) as $foreignKey) {
            DB::statement(sprintf(
                'ALTER TABLE `%s` DROP FOREIGN KEY `%s`',
                str_replace('`', '``', $tableName),
                str_replace('`', '``', $foreignKey->CONSTRAINT_NAME),
            ));
        }
    }

    /** @return array<int, object> */
    private function foreignKeysForColumn(string $tableName, string $column): array
    {
        return DB::select(
            'SELECT CONSTRAINT_NAME, REFERENCED_TABLE_NAME
             FROM information_schema.KEY_COLUMN_USAGE
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME = ?
               AND COLUMN_NAME = ?
               AND REFERENCED_TABLE_NAME IS NOT NULL',
            [$tableName, $column]
        );
    }

    private function columnInfo(string $tableName, string $column): ?object
    {
        return DB::selectOne(
            'SELECT IS_NULLABLE
             FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME = ?
               AND COLUMN_NAME = ?',
            [$tableName, $column]
        );
    }
};
