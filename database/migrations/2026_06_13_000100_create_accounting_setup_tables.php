<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('chart_of_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('code', 50);
            $table->string('name');
            $table->string('type', 30);
            $table->string('normal_balance', 10);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['company_id', 'code'], 'coa_company_code_unique');
        });

        Schema::create('money_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('chart_of_account_id')->constrained('chart_of_accounts')->restrictOnDelete();
            $table->string('name');
            $table->string('kind', 30);
            $table->decimal('opening_balance', 20, 2)->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['company_id', 'name'], 'money_company_name_unique');
            $table->unique(['company_id', 'chart_of_account_id'], 'money_company_coa_unique');
        });

        Schema::create('parties', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('code', 50);
            $table->string('name');
            $table->string('type', 30);
            $table->foreignId('receivable_account_id')->nullable()->constrained('chart_of_accounts')->nullOnDelete();
            $table->foreignId('payable_account_id')->nullable()->constrained('chart_of_accounts')->nullOnDelete();
            $table->decimal('opening_balance', 20, 2)->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['company_id', 'code'], 'party_company_code_unique');
        });

        Schema::create('accounting_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('code', 50);
            $table->string('name');
            $table->string('category', 30);
            $table->string('debit_source', 40);
            $table->string('credit_source', 40);
            $table->boolean('party_required')->default(false);
            $table->string('party_type', 30)->default('Any');
            $table->boolean('money_required')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['company_id', 'code'], 'rule_company_code_unique');
        });

        Schema::create('transaction_heads', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('accounting_rule_id')->constrained()->restrictOnDelete();
            $table->foreignId('posting_account_id')->constrained('chart_of_accounts')->restrictOnDelete();
            $table->string('code', 50);
            $table->string('name');
            $table->string('category', 30);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['company_id', 'code'], 'head_company_code_unique');
        });

        Schema::create('document_sequences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('category', 30);
            $table->string('prefix', 10);
            $table->unsignedBigInteger('next_number')->default(1);
            $table->unsignedTinyInteger('padding')->default(4);
            $table->timestamps();
            $table->unique(['company_id', 'category'], 'sequence_company_category_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('document_sequences');
        Schema::dropIfExists('transaction_heads');
        Schema::dropIfExists('accounting_rules');
        Schema::dropIfExists('parties');
        Schema::dropIfExists('money_accounts');
        Schema::dropIfExists('chart_of_accounts');
    }
};
