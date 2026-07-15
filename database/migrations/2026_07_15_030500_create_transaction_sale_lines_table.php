<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('transaction_sale_lines')) {
            return;
        }

        Schema::create('transaction_sale_lines', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('transaction_id')->constrained('transactions')->cascadeOnDelete();
            $table->string('business_area', 80)->nullable();
            $table->string('item_name');
            $table->string('unit', 50)->nullable();
            $table->decimal('quantity', 20, 4)->default(0);
            $table->decimal('rate', 20, 2)->default(0);
            $table->decimal('discount', 20, 2)->default(0);
            $table->decimal('line_total', 20, 2)->default(0);
            $table->unsignedSmallInteger('sequence')->default(1);
            $table->timestamps();

            $table->index(['company_id', 'business_area'], 'tx_sale_lines_company_area_idx');
            $table->index(['transaction_id', 'sequence'], 'tx_sale_lines_transaction_sequence_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transaction_sale_lines');
    }
};
