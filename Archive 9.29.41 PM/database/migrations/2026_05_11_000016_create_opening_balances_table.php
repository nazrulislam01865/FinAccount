<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('opening_balances', function (Blueprint $table) {
            $table->id();

            $table->foreignId('company_id')
                ->nullable()
                ->constrained('companies')
                ->nullOnDelete();

            $table->foreignId('financial_year_id')
                ->constrained('financial_years')
                ->restrictOnDelete();

            $table->string('branch_location')->nullable();

            $table->foreignId('account_id')
                ->constrained('chart_of_accounts')
                ->restrictOnDelete();

            $table->foreignId('party_id')
                ->nullable()
                ->constrained('parties')
                ->restrictOnDelete();

            $table->decimal('debit_opening', 18, 2)->default(0);
            $table->decimal('credit_opening', 18, 2)->default(0);

            $table->string('remarks')->nullable();

            $table->enum('status', ['Draft', 'Final'])->default('Draft');

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

            $table->index(['financial_year_id', 'branch_location'], 'ob_year_branch_idx');
            $table->index(['account_id', 'party_id'], 'ob_account_party_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('opening_balances');
    }
};
