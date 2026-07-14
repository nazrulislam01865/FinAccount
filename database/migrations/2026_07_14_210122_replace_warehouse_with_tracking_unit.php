<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // This migration keeps the database naming used by the application (`tracking_unit_id`)
        // but the value is still the Feed Setup warehouse/location id. It must NOT point to
        // feed_business_tracking_units because stock is stored by warehouse/location.
        $this->renameWarehouseColumn('transactions', 'warehouse_id', 'tracking_unit_id');
        $this->ensureForeign('transactions', 'tracking_unit_id', 'feed_warehouses', 'tx_tracking_unit_fw_fk');

        $this->renameWarehouseColumn('feed_settings', 'default_warehouse_id', 'default_tracking_unit_id');
        $this->ensureForeign('feed_settings', 'default_tracking_unit_id', 'feed_warehouses', 'feed_settings_default_wh_fk');

        $this->renameWarehouseColumn('feed_documents', 'warehouse_id', 'tracking_unit_id');
        $this->ensureIndex('feed_documents', 'feed_documents_company_tracking_idx', ['company_id', 'tracking_unit_id']);
        $this->ensureForeign('feed_documents', 'tracking_unit_id', 'feed_warehouses', 'feed_documents_tracking_fw_fk');

        $this->renameWarehouseColumn('feed_stock_balances', 'warehouse_id', 'tracking_unit_id');
        $this->dropIndexIfExists('feed_stock_balances', 'feed_stock_balance_unique');
        $this->dropIndexIfExists('feed_stock_balances', 'feed_stock_balances_company_id_warehouse_id_index');
        $this->dropIndexIfExists('feed_stock_balances', 'feed_stock_balances_company_id_tracking_unit_id_index');
        $this->ensureIndex('feed_stock_balances', 'feed_stock_balances_company_tracking_idx', ['company_id', 'tracking_unit_id']);
        $this->ensureUnique('feed_stock_balances', 'feed_stock_balance_unique', ['company_id', 'feed_item_id', 'tracking_unit_id']);
        $this->ensureForeign('feed_stock_balances', 'tracking_unit_id', 'feed_warehouses', 'feed_stock_balances_tracking_fw_fk');

        $this->renameWarehouseColumn('feed_stock_movements', 'warehouse_id', 'tracking_unit_id');
        $this->dropIndexIfExists('feed_stock_movements', 'feed_movement_stock_lookup');
        $this->ensureIndex('feed_stock_movements', 'feed_movement_stock_lookup', ['company_id', 'feed_item_id', 'tracking_unit_id']);
        $this->ensureForeign('feed_stock_movements', 'tracking_unit_id', 'feed_warehouses', 'feed_stock_movements_tracking_fw_fk');
    }

    public function down(): void
    {
        $this->dropForeignsForColumn('feed_stock_movements', 'tracking_unit_id');
        $this->dropIndexIfExists('feed_stock_movements', 'feed_movement_stock_lookup');
        $this->renameWarehouseColumn('feed_stock_movements', 'tracking_unit_id', 'warehouse_id');
        $this->ensureIndex('feed_stock_movements', 'feed_movement_stock_lookup', ['company_id', 'feed_item_id', 'warehouse_id']);
        $this->ensureForeign('feed_stock_movements', 'warehouse_id', 'feed_warehouses', 'feed_stock_movements_warehouse_fk');

        $this->dropForeignsForColumn('feed_stock_balances', 'tracking_unit_id');
        $this->dropIndexIfExists('feed_stock_balances', 'feed_stock_balance_unique');
        $this->dropIndexIfExists('feed_stock_balances', 'feed_stock_balances_company_tracking_idx');
        $this->renameWarehouseColumn('feed_stock_balances', 'tracking_unit_id', 'warehouse_id');
        $this->ensureIndex('feed_stock_balances', 'feed_stock_balances_company_id_warehouse_id_index', ['company_id', 'warehouse_id']);
        $this->ensureUnique('feed_stock_balances', 'feed_stock_balance_unique', ['company_id', 'feed_item_id', 'warehouse_id']);
        $this->ensureForeign('feed_stock_balances', 'warehouse_id', 'feed_warehouses', 'feed_stock_balances_warehouse_fk');

        $this->dropForeignsForColumn('feed_documents', 'tracking_unit_id');
        $this->dropIndexIfExists('feed_documents', 'feed_documents_company_tracking_idx');
        $this->renameWarehouseColumn('feed_documents', 'tracking_unit_id', 'warehouse_id');
        $this->ensureIndex('feed_documents', 'feed_documents_company_id_warehouse_id_index', ['company_id', 'warehouse_id']);
        $this->ensureForeign('feed_documents', 'warehouse_id', 'feed_warehouses', 'feed_documents_warehouse_fk');

        $this->dropForeignsForColumn('feed_settings', 'default_tracking_unit_id');
        $this->renameWarehouseColumn('feed_settings', 'default_tracking_unit_id', 'default_warehouse_id');
        $this->ensureForeign('feed_settings', 'default_warehouse_id', 'feed_warehouses', 'feed_settings_default_warehouse_fk');

        $this->dropForeignsForColumn('transactions', 'tracking_unit_id');
        $this->renameWarehouseColumn('transactions', 'tracking_unit_id', 'warehouse_id');
        $this->ensureForeign('transactions', 'warehouse_id', 'feed_warehouses', 'transactions_warehouse_fk');
    }

    private function renameWarehouseColumn(string $table, string $from, string $to): void
    {
        if (! Schema::hasTable($table)) {
            return;
        }

        if (Schema::hasColumn($table, $from)) {
            $this->dropForeignsForColumn($table, $from);
            Schema::table($table, function (Blueprint $blueprint) use ($from, $to): void {
                $blueprint->renameColumn($from, $to);
            });
        }

        if (Schema::hasColumn($table, $to)) {
            $this->dropForeignsForColumn($table, $to);
        }
    }

    /** @param array<int, string> $columns */
    private function ensureIndex(string $table, string $name, array $columns): void
    {
        if (! Schema::hasTable($table) || $this->indexExists($table, $name)) {
            return;
        }

        foreach ($columns as $column) {
            if (! Schema::hasColumn($table, $column)) {
                return;
            }
        }

        Schema::table($table, function (Blueprint $blueprint) use ($columns, $name): void {
            $blueprint->index($columns, $name);
        });
    }

    /** @param array<int, string> $columns */
    private function ensureUnique(string $table, string $name, array $columns): void
    {
        if (! Schema::hasTable($table) || $this->indexExists($table, $name)) {
            return;
        }

        foreach ($columns as $column) {
            if (! Schema::hasColumn($table, $column)) {
                return;
            }
        }

        Schema::table($table, function (Blueprint $blueprint) use ($columns, $name): void {
            $blueprint->unique($columns, $name);
        });
    }

    private function ensureForeign(string $table, string $column, string $referencesTable, string $constraintName): void
    {
        if (! Schema::hasTable($table) || ! Schema::hasColumn($table, $column)) {
            return;
        }

        $this->dropForeignsForColumn($table, $column);

        Schema::table($table, function (Blueprint $blueprint) use ($column, $referencesTable, $constraintName): void {
            $blueprint->foreign($column, $constraintName)
                ->references('id')
                ->on($referencesTable)
                ->restrictOnDelete();
        });
    }

    private function dropForeignsForColumn(string $table, string $column): void
    {
        if (! Schema::hasTable($table) || ! Schema::hasColumn($table, $column)) {
            return;
        }

        $constraints = DB::table('information_schema.KEY_COLUMN_USAGE')
            ->selectRaw('CONSTRAINT_NAME as constraint_name')
            ->whereRaw('TABLE_SCHEMA = DATABASE()')
            ->where('TABLE_NAME', $table)
            ->where('COLUMN_NAME', $column)
            ->whereNotNull('REFERENCED_TABLE_NAME')
            ->pluck('constraint_name')
            ->filter()
            ->unique()
            ->values();

        foreach ($constraints as $constraint) {
            DB::statement('ALTER TABLE `'.$table.'` DROP FOREIGN KEY `'.$constraint.'`');
        }
    }

    private function dropIndexIfExists(string $table, string $index): void
    {
        if (! Schema::hasTable($table) || ! $this->indexExists($table, $index)) {
            return;
        }

        DB::statement('ALTER TABLE `'.$table.'` DROP INDEX `'.$index.'`');
    }

    private function indexExists(string $table, string $index): bool
    {
        if (! Schema::hasTable($table)) {
            return false;
        }

        return DB::table('information_schema.STATISTICS')
            ->whereRaw('TABLE_SCHEMA = DATABASE()')
            ->where('TABLE_NAME', $table)
            ->where('INDEX_NAME', $index)
            ->exists();
    }
};
