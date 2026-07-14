<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Transactions table
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropForeign(['warehouse_id']);
            $table->renameColumn('warehouse_id', 'tracking_unit_id');
        });
        Schema::table('transactions', function (Blueprint $table) {
            $table->foreign('tracking_unit_id')->references('id')->on('feed_business_tracking_units')->restrictOnDelete();
        });

        // 2. Feed Settings table
        Schema::table('feed_settings', function (Blueprint $table) {
            $table->dropForeign(['default_warehouse_id']);
            $table->renameColumn('default_warehouse_id', 'default_tracking_unit_id');
        });
        Schema::table('feed_settings', function (Blueprint $table) {
            $table->foreign('default_tracking_unit_id')->references('id')->on('feed_business_tracking_units')->restrictOnDelete();
        });

        // 3. Feed Stock Balances table
        Schema::table('feed_stock_balances', function (Blueprint $table) {
            $table->dropForeign(['warehouse_id']);
            $table->dropUnique('feed_stock_balance_unique');
            $table->dropIndex(['company_id', 'warehouse_id']);
            $table->renameColumn('warehouse_id', 'tracking_unit_id');
        });
        Schema::table('feed_stock_balances', function (Blueprint $table) {
            $table->index(['company_id', 'tracking_unit_id']);
            $table->unique(['company_id', 'feed_item_id', 'tracking_unit_id'], 'feed_stock_balance_unique');
            $table->foreign('tracking_unit_id')->references('id')->on('feed_business_tracking_units')->restrictOnDelete();
        });

        // 4. Feed Movements table
        Schema::table('feed_movements', function (Blueprint $table) {
            $table->dropForeign(['warehouse_id']);
            $table->dropIndex('feed_movement_stock_lookup');
            $table->renameColumn('warehouse_id', 'tracking_unit_id');
        });
        Schema::table('feed_movements', function (Blueprint $table) {
            $table->index(['company_id', 'feed_item_id', 'tracking_unit_id'], 'feed_movement_stock_lookup');
            $table->foreign('tracking_unit_id')->references('id')->on('feed_business_tracking_units')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        // Reverse everything (simplistic version)
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropForeign(['tracking_unit_id']);
            $table->renameColumn('tracking_unit_id', 'warehouse_id');
        });
        Schema::table('transactions', function (Blueprint $table) {
            $table->foreign('warehouse_id')->references('id')->on('feed_warehouses')->restrictOnDelete();
        });

        Schema::table('feed_settings', function (Blueprint $table) {
            $table->dropForeign(['default_tracking_unit_id']);
            $table->renameColumn('default_tracking_unit_id', 'default_warehouse_id');
        });
        Schema::table('feed_settings', function (Blueprint $table) {
            $table->foreign('default_warehouse_id')->references('id')->on('feed_warehouses')->restrictOnDelete();
        });

        Schema::table('feed_stock_balances', function (Blueprint $table) {
            $table->dropForeign(['tracking_unit_id']);
            $table->dropUnique('feed_stock_balance_unique');
            $table->dropIndex(['company_id', 'tracking_unit_id']);
            $table->renameColumn('tracking_unit_id', 'warehouse_id');
        });
        Schema::table('feed_stock_balances', function (Blueprint $table) {
            $table->index(['company_id', 'warehouse_id']);
            $table->unique(['company_id', 'feed_item_id', 'warehouse_id'], 'feed_stock_balance_unique');
            $table->foreign('warehouse_id')->references('id')->on('feed_warehouses')->restrictOnDelete();
        });

        Schema::table('feed_movements', function (Blueprint $table) {
            $table->dropForeign(['tracking_unit_id']);
            $table->dropIndex('feed_movement_stock_lookup');
            $table->renameColumn('tracking_unit_id', 'warehouse_id');
        });
        Schema::table('feed_movements', function (Blueprint $table) {
            $table->index(['company_id', 'feed_item_id', 'warehouse_id'], 'feed_movement_stock_lookup');
            $table->foreign('warehouse_id')->references('id')->on('feed_warehouses')->restrictOnDelete();
        });
    }
};
