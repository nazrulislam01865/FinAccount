<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('voucher_numbering_rules', function (Blueprint $table) {
            $table->id();

            $table->foreignId('company_id')
                ->nullable()
                ->constrained('companies')
                ->nullOnDelete();

            $table->foreignId('financial_year_id')
                ->constrained('financial_years')
                ->restrictOnDelete();

            $table->enum('voucher_type', [
                'Payment Voucher',
                'Receipt Voucher',
                'Journal Voucher',
                'Contra / Transfer Voucher',
                'Draft Voucher',
            ]);

            $table->string('prefix', 10);
            $table->string('format_template', 80);
            $table->unsignedBigInteger('starting_number')->default(1);
            $table->unsignedTinyInteger('number_length')->default(5);
            $table->unsignedBigInteger('last_number')->default(0);
            $table->boolean('reset_every_year')->default(true);
            $table->text('used_for')->nullable();

            $table->enum('status', ['Active', 'Inactive'])->default('Active');

            $table->foreignId('created_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->foreignId('updated_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamps();
            $table->softDeletes();

            $table->unique(
                ['company_id', 'financial_year_id', 'voucher_type'],
                'vn_company_year_type_unique'
            );

            $table->unique(
                ['company_id', 'financial_year_id', 'prefix'],
                'vn_company_year_prefix_unique'
            );

            $table->index(
                ['voucher_type', 'financial_year_id', 'status'],
                'vn_generator_lookup_idx'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('voucher_numbering_rules');
    }
};
