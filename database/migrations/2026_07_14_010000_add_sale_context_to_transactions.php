<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transactions', function (Blueprint $table): void {
            $table->string('selling_type', 30)->nullable()->after('category');
            $table->foreignId('warehouse_id')
                ->nullable()
                ->after('party_id')
                ->constrained('feed_warehouses')
                ->restrictOnDelete();

            $table->index(['company_id', 'selling_type'], 'transaction_company_selling_type_index');
        });
    }

    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table): void {
            $table->dropIndex('transaction_company_selling_type_index');
            $table->dropConstrainedForeignId('warehouse_id');
            $table->dropColumn('selling_type');
        });
    }
};
