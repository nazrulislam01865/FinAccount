<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Rename the column used by the feed forms to tracking_unit_id.
        // It still references feed_warehouses because the application code loads FeedWarehouse.
        $this->renameColumnAndKeepWarehouseReference('transactions', 'warehouse_id', 'tracking_unit_id');
        $this->renameColumnAndKeepWarehouseReference('feed_documents', 'warehouse_id', 'tracking_unit_id');
        $this->renameColumnAndKeepWarehouseReference('feed_stock_balances', 'warehouse_id', 'tracking_unit_id');
        $this->renameColumnAndKeepWarehouseReference('feed_stock_movements', 'warehouse_id', 'tracking_unit_id');
        $this->renameColumnAndKeepWarehouseReference('feed_settings', 'default_warehouse_id', 'default_tracking_unit_id');
    }

    public function down(): void
    {
        $this->renameColumnAndKeepWarehouseReference('transactions', 'tracking_unit_id', 'warehouse_id');
        $this->renameColumnAndKeepWarehouseReference('feed_documents', 'tracking_unit_id', 'warehouse_id');
        $this->renameColumnAndKeepWarehouseReference('feed_stock_balances', 'tracking_unit_id', 'warehouse_id');
        $this->renameColumnAndKeepWarehouseReference('feed_stock_movements', 'tracking_unit_id', 'warehouse_id');
        $this->renameColumnAndKeepWarehouseReference('feed_settings', 'default_tracking_unit_id', 'default_warehouse_id');
    }

    private function renameColumnAndKeepWarehouseReference(string $tableName, string $fromColumn, string $toColumn): void
    {
        if (! Schema::hasTable($tableName)) {
            return;
        }

        if (Schema::hasColumn($tableName, $fromColumn)) {
            $this->dropForeignKeysForColumn($tableName, $fromColumn);

            Schema::table($tableName, function (Blueprint $table) use ($fromColumn, $toColumn): void {
                $table->renameColumn($fromColumn, $toColumn);
            });
        }

        if (Schema::hasColumn($tableName, $toColumn)) {
            $this->ensureWarehouseForeignKey($tableName, $toColumn);
        }
    }

    private function ensureWarehouseForeignKey(string $tableName, string $column): void
    {
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
};
