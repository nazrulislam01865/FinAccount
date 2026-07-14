<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // This migration may be re-run after a previous failed attempt. Keep it
        // idempotent and repair any partially-created foreign keys.
        $this->renameWarehouseColumn('transactions', 'warehouse_id', 'tracking_unit_id', 'tx_tracking_unit_wh_fk');
        $this->renameWarehouseColumn('feed_settings', 'default_warehouse_id', 'default_tracking_unit_id', 'feed_settings_def_track_wh_fk');
        $this->renameWarehouseColumn('feed_documents', 'warehouse_id', 'tracking_unit_id', 'feed_docs_tracking_wh_fk', [
            'new_index' => ['columns' => ['company_id', 'tracking_unit_id'], 'name' => 'feed_documents_company_tracking_idx'],
        ]);

        $this->renameFeedStockBalances();
        $this->renameFeedStockMovements();
    }

    public function down(): void
    {
        $this->renameWarehouseColumn('transactions', 'tracking_unit_id', 'warehouse_id', 'tx_warehouse_fk');
        $this->renameWarehouseColumn('feed_settings', 'default_tracking_unit_id', 'default_warehouse_id', 'feed_settings_def_wh_fk');
        $this->renameWarehouseColumn('feed_documents', 'tracking_unit_id', 'warehouse_id', 'feed_docs_warehouse_fk', [
            'new_index' => ['columns' => ['company_id', 'warehouse_id'], 'name' => 'feed_documents_company_id_warehouse_id_index'],
        ]);

        $this->renameFeedStockBalances(true);
        $this->renameFeedStockMovements(true);
    }

    private function renameWarehouseColumn(
        string $table,
        string $from,
        string $to,
        string $foreignName,
        array $options = []
    ): void {
        if (! Schema::hasTable($table)) {
            return;
        }

        if (Schema::hasColumn($table, $from) && ! Schema::hasColumn($table, $to)) {
            $this->dropForeignKeysForColumn($table, $from);

            foreach ($options['old_indexes'] ?? [] as $oldIndex) {
                $this->dropIndexIfExists($table, $oldIndex);
            }

            Schema::table($table, function (Blueprint $tableBlueprint) use ($from, $to): void {
                $tableBlueprint->renameColumn($from, $to);
            });
        }

        if (! Schema::hasColumn($table, $to)) {
            return;
        }

        if (isset($options['new_index'])) {
            $this->addIndexIfMissing($table, $options['new_index']['columns'], $options['new_index']['name']);
        }

        $this->ensureWarehouseForeign($table, $to, $foreignName);
    }

    private function renameFeedStockBalances(bool $reverse = false): void
    {
        $table = 'feed_stock_balances';

        if (! Schema::hasTable($table)) {
            return;
        }

        $from = $reverse ? 'tracking_unit_id' : 'warehouse_id';
        $to = $reverse ? 'warehouse_id' : 'tracking_unit_id';
        $locationIndex = $reverse ? 'fsb_tracking_unit_fk_idx' : 'fsb_warehouse_fk_idx';
        $newLocationIndex = $reverse ? 'fsb_warehouse_fk_idx' : 'fsb_tracking_unit_fk_idx';
        $companyLocationIndex = $reverse ? 'feed_stock_bal_company_warehouse_idx' : 'feed_stock_bal_company_tracking_idx';

        if (Schema::hasColumn($table, $from) && ! Schema::hasColumn($table, $to)) {
            // The old unique index can be used by MySQL to support foreign keys.
            // Drop/recreate those FKs explicitly before replacing the unique index.
            $this->dropForeignKeysForColumn($table, 'company_id');
            $this->dropForeignKeysForColumn($table, 'feed_item_id');
            $this->dropForeignKeysForColumn($table, $from);

            $this->addIndexIfMissing($table, ['company_id'], 'fsb_company_fk_idx');
            $this->addIndexIfMissing($table, ['feed_item_id'], 'fsb_item_fk_idx');
            $this->addIndexIfMissing($table, [$from], $locationIndex);
            $this->dropIndexIfExists($table, 'feed_stock_balance_unique');

            Schema::table($table, function (Blueprint $tableBlueprint) use ($from, $to): void {
                $tableBlueprint->renameColumn($from, $to);
            });
        }

        if (! Schema::hasColumn($table, $to)) {
            return;
        }

        $this->dropForeignKeysForColumn($table, 'company_id');
        $this->dropForeignKeysForColumn($table, 'feed_item_id');
        $this->dropForeignKeysForColumn($table, $to);

        $this->addIndexIfMissing($table, ['company_id'], 'fsb_company_fk_idx');
        $this->addIndexIfMissing($table, ['feed_item_id'], 'fsb_item_fk_idx');
        $this->addIndexIfMissing($table, [$to], $newLocationIndex);
        $this->addIndexIfMissing($table, ['company_id', $to], $companyLocationIndex);

        $this->dropIndexIfExists($table, 'feed_stock_balance_unique');
        $this->addUniqueIfMissing($table, ['company_id', 'feed_item_id', $to], 'feed_stock_balance_unique');

        $this->addForeignIfMissing($table, 'company_id', 'companies', 'id', 'fsb_company_fk', 'CASCADE');
        $this->addForeignIfMissing($table, 'feed_item_id', 'feed_items', 'id', 'fsb_item_fk', 'RESTRICT');
        $this->addForeignIfMissing($table, $to, 'feed_warehouses', 'id', $reverse ? 'fsb_warehouse_fk' : 'fsb_tracking_wh_fk', 'RESTRICT');
    }

    private function renameFeedStockMovements(bool $reverse = false): void
    {
        $table = 'feed_stock_movements';

        if (! Schema::hasTable($table)) {
            return;
        }

        $from = $reverse ? 'tracking_unit_id' : 'warehouse_id';
        $to = $reverse ? 'warehouse_id' : 'tracking_unit_id';
        $lookupColumns = ['company_id', 'feed_item_id', $to];

        if (Schema::hasColumn($table, $from) && ! Schema::hasColumn($table, $to)) {
            $this->dropForeignKeysForColumn($table, 'company_id');
            $this->dropForeignKeysForColumn($table, 'feed_item_id');
            $this->dropForeignKeysForColumn($table, $from);

            $this->addIndexIfMissing($table, ['company_id'], 'fsm_company_fk_idx');
            $this->addIndexIfMissing($table, ['feed_item_id'], 'fsm_item_fk_idx');
            $this->addIndexIfMissing($table, [$from], 'fsm_location_fk_idx');
            $this->dropIndexIfExists($table, 'feed_movement_stock_lookup');

            Schema::table($table, function (Blueprint $tableBlueprint) use ($from, $to): void {
                $tableBlueprint->renameColumn($from, $to);
            });
        }

        if (! Schema::hasColumn($table, $to)) {
            return;
        }

        $this->dropForeignKeysForColumn($table, 'company_id');
        $this->dropForeignKeysForColumn($table, 'feed_item_id');
        $this->dropForeignKeysForColumn($table, $to);

        $this->addIndexIfMissing($table, ['company_id'], 'fsm_company_fk_idx');
        $this->addIndexIfMissing($table, ['feed_item_id'], 'fsm_item_fk_idx');
        $this->addIndexIfMissing($table, [$to], 'fsm_location_fk_idx');
        $this->dropIndexIfExists($table, 'feed_movement_stock_lookup');
        $this->addIndexIfMissing($table, $lookupColumns, 'feed_movement_stock_lookup');

        $this->addForeignIfMissing($table, 'company_id', 'companies', 'id', 'fsm_company_fk', 'CASCADE');
        $this->addForeignIfMissing($table, 'feed_item_id', 'feed_items', 'id', 'fsm_item_fk', 'RESTRICT');
        $this->addForeignIfMissing($table, $to, 'feed_warehouses', 'id', $reverse ? 'fsm_warehouse_fk' : 'fsm_tracking_wh_fk', 'RESTRICT');
    }

    private function ensureWarehouseForeign(string $table, string $column, string $constraintName): void
    {
        if ($this->hasForeignKey($table, $column, 'feed_warehouses')) {
            return;
        }

        $this->dropForeignKeysForColumn($table, $column);
        $this->addIndexIfMissing($table, [$column], $constraintName.'_idx');
        $this->addForeignIfMissing($table, $column, 'feed_warehouses', 'id', $constraintName, 'RESTRICT');
    }

    private function addForeignIfMissing(
        string $table,
        string $column,
        string $referencedTable,
        string $referencedColumn,
        string $constraintName,
        string $onDelete = 'RESTRICT'
    ): void {
        if ($this->hasForeignKey($table, $column, $referencedTable)) {
            return;
        }

        $this->dropForeignKeyByName($table, $constraintName);

        DB::statement(sprintf(
            'ALTER TABLE `%s` ADD CONSTRAINT `%s` FOREIGN KEY (`%s`) REFERENCES `%s` (`%s`) ON DELETE %s',
            $table,
            $constraintName,
            $column,
            $referencedTable,
            $referencedColumn,
            $onDelete
        ));
    }

    private function addIndexIfMissing(string $table, array $columns, string $indexName): void
    {
        if ($this->indexExists($table, $indexName)) {
            return;
        }

        DB::statement(sprintf(
            'ALTER TABLE `%s` ADD INDEX `%s` (%s)',
            $table,
            $indexName,
            implode(', ', array_map(fn (string $column): string => '`'.$column.'`', $columns))
        ));
    }

    private function addUniqueIfMissing(string $table, array $columns, string $indexName): void
    {
        if ($this->indexExists($table, $indexName)) {
            return;
        }

        DB::statement(sprintf(
            'ALTER TABLE `%s` ADD UNIQUE `%s` (%s)',
            $table,
            $indexName,
            implode(', ', array_map(fn (string $column): string => '`'.$column.'`', $columns))
        ));
    }

    private function dropIndexIfExists(string $table, string $indexName): void
    {
        if (! $this->indexExists($table, $indexName)) {
            return;
        }

        DB::statement(sprintf('ALTER TABLE `%s` DROP INDEX `%s`', $table, $indexName));
    }

    private function indexExists(string $table, string $indexName): bool
    {
        return DB::table('information_schema.statistics')
            ->whereRaw('table_schema = database()')
            ->where('table_name', $table)
            ->where('index_name', $indexName)
            ->exists();
    }

    private function hasForeignKey(string $table, string $column, ?string $referencedTable = null): bool
    {
        $query = DB::table('information_schema.key_column_usage')
            ->whereRaw('table_schema = database()')
            ->where('table_name', $table)
            ->where('column_name', $column)
            ->whereNotNull('referenced_table_name');

        if ($referencedTable !== null) {
            $query->where('referenced_table_name', $referencedTable);
        }

        return $query->exists();
    }

    private function dropForeignKeysForColumn(string $table, string $column): void
    {
        $constraints = DB::table('information_schema.key_column_usage')
            ->whereRaw('table_schema = database()')
            ->where('table_name', $table)
            ->where('column_name', $column)
            ->whereNotNull('referenced_table_name')
            ->pluck('constraint_name')
            ->unique()
            ->values();

        foreach ($constraints as $constraint) {
            $this->dropForeignKeyByName($table, (string) $constraint);
        }
    }

    private function dropForeignKeyByName(string $table, string $constraintName): void
    {
        $exists = DB::table('information_schema.table_constraints')
            ->whereRaw('table_schema = database()')
            ->where('table_name', $table)
            ->where('constraint_name', $constraintName)
            ->where('constraint_type', 'FOREIGN KEY')
            ->exists();

        if (! $exists) {
            return;
        }

        DB::statement(sprintf('ALTER TABLE `%s` DROP FOREIGN KEY `%s`', $table, $constraintName));
    }
};
