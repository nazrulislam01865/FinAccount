<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('ledger_mapping_rules', function (Blueprint $table) {
            $table->id();

            $table->foreignId('company_id')
                ->nullable()
                ->constrained()
                ->nullOnDelete();

            $table->foreignId('transaction_head_id')
                ->constrained()
                ->restrictOnDelete();

            $table->foreignId('settlement_type_id')
                ->constrained()
                ->restrictOnDelete();

            $table->foreignId('debit_account_id')
                ->constrained('chart_of_accounts')
                ->restrictOnDelete();

            $table->foreignId('credit_account_id')
                ->constrained('chart_of_accounts')
                ->restrictOnDelete();

            $table->enum('party_ledger_effect', [
                'No Effect',
                'Increase Liability',
                'Decrease Liability',
                'Increase Receivable',
                'Decrease Receivable',
                'Increase Advance Asset',
                'Decrease Advance Asset',
                'Increase Advance Liability',
                'Decrease Advance Liability',
            ])->default('No Effect');

            $table->boolean('auto_post')->default(true);
            $table->text('description')->nullable();

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
                ['company_id', 'transaction_head_id', 'settlement_type_id'],
                'lm_company_head_settlement_unique'
            );

            $table->index(
                ['transaction_head_id', 'settlement_type_id', 'status'],
                'lm_resolver_lookup_idx'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ledger_mapping_rules');
    }
};
