<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('feed_warehouses', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('code', 50);
            $table->string('name');
            $table->string('location')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['company_id', 'code'], 'feed_warehouse_company_code_unique');
            $table->unique(['company_id', 'name'], 'feed_warehouse_company_name_unique');
        });

        Schema::create('feed_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('code', 50);
            $table->string('name');
            $table->string('category', 100)->nullable();
            $table->string('brand', 100)->nullable();
            $table->decimal('pack_size', 20, 4)->default(1);
            $table->string('base_unit', 20)->default('KG');
            $table->decimal('default_purchase_price', 20, 2)->default(0);
            $table->decimal('default_sale_price', 20, 2)->default(0);
            $table->decimal('reorder_level', 20, 4)->default(0);
            $table->boolean('track_batch')->default(true);
            $table->boolean('track_expiry')->default(true);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['company_id', 'code'], 'feed_item_company_code_unique');
            $table->index(['company_id', 'name']);
        });

        Schema::create('feed_settings', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('purchase_transaction_head_id')->constrained('transaction_heads')->restrictOnDelete();
            $table->foreignId('sale_transaction_head_id')->constrained('transaction_heads')->restrictOnDelete();
            $table->foreignId('cogs_account_id')->constrained('chart_of_accounts')->restrictOnDelete();
            $table->foreignId('default_warehouse_id')->nullable()->constrained('feed_warehouses')->restrictOnDelete();
            $table->timestamps();
        });

        Schema::create('feed_documents', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('transaction_id')->unique()->constrained()->restrictOnDelete();
            $table->foreignId('warehouse_id')->constrained('feed_warehouses')->restrictOnDelete();
            $table->foreignId('party_id')->constrained('parties')->restrictOnDelete();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->string('document_type', 20);
            $table->string('external_invoice_no', 100)->nullable();
            $table->string('reference', 100)->nullable();
            $table->string('cost_allocation', 30)->nullable();
            $table->decimal('subtotal', 20, 2)->default(0);
            $table->decimal('transport_cost', 20, 2)->default(0);
            $table->decimal('other_cost', 20, 2)->default(0);
            $table->decimal('delivery_charge', 20, 2)->default(0);
            $table->decimal('overall_discount', 20, 2)->default(0);
            $table->decimal('total_amount', 20, 2)->default(0);
            $table->decimal('cogs_total', 20, 2)->default(0);
            $table->timestamps();

            $table->index(['company_id', 'document_type']);
            $table->index(['company_id', 'warehouse_id']);
        });

        Schema::create('feed_document_lines', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('feed_document_id')->constrained()->cascadeOnDelete();
            $table->foreignId('feed_item_id')->constrained('feed_items')->restrictOnDelete();
            $table->decimal('quantity', 20, 4);
            $table->string('unit', 20);
            $table->decimal('base_quantity', 20, 4);
            $table->decimal('rate', 20, 2);
            $table->decimal('discount', 20, 2)->default(0);
            $table->decimal('line_total', 20, 2);
            $table->decimal('allocated_cost', 20, 2)->default(0);
            $table->decimal('unit_cost', 20, 6)->default(0);
            $table->decimal('cogs_total', 20, 2)->default(0);
            $table->string('batch_no', 100)->nullable();
            $table->date('expiry_date')->nullable();
            $table->timestamps();

            $table->index(['company_id', 'feed_item_id']);
        });

        Schema::create('feed_stock_balances', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('feed_item_id')->constrained('feed_items')->restrictOnDelete();
            $table->foreignId('warehouse_id')->constrained('feed_warehouses')->restrictOnDelete();
            $table->decimal('quantity', 20, 4)->default(0);
            $table->decimal('average_cost', 20, 6)->default(0);
            $table->timestamps();

            $table->unique(['company_id', 'feed_item_id', 'warehouse_id'], 'feed_stock_balance_unique');
        });

        Schema::create('feed_stock_movements', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('feed_document_id')->constrained()->cascadeOnDelete();
            $table->foreignId('transaction_id')->constrained()->restrictOnDelete();
            $table->foreignId('feed_item_id')->constrained('feed_items')->restrictOnDelete();
            $table->foreignId('warehouse_id')->constrained('feed_warehouses')->restrictOnDelete();
            $table->string('movement_type', 30);
            $table->date('movement_date');
            $table->decimal('quantity_in', 20, 4)->default(0);
            $table->decimal('quantity_out', 20, 4)->default(0);
            $table->decimal('unit_cost', 20, 6)->default(0);
            $table->decimal('total_value', 20, 2)->default(0);
            $table->decimal('quantity_before', 20, 4)->default(0);
            $table->decimal('quantity_after', 20, 4)->default(0);
            $table->decimal('average_cost_before', 20, 6)->default(0);
            $table->decimal('average_cost_after', 20, 6)->default(0);
            $table->string('reference', 100)->nullable();
            $table->timestamps();

            $table->index(['company_id', 'movement_date']);
            $table->index(['company_id', 'feed_item_id', 'warehouse_id'], 'feed_movement_stock_lookup');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('feed_stock_movements');
        Schema::dropIfExists('feed_stock_balances');
        Schema::dropIfExists('feed_document_lines');
        Schema::dropIfExists('feed_documents');
        Schema::dropIfExists('feed_settings');
        Schema::dropIfExists('feed_items');
        Schema::dropIfExists('feed_warehouses');
    }
};
