<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transactions', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('transaction_head_id')->constrained()->restrictOnDelete();
            $table->foreignId('money_account_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('party_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->string('voucher_no', 50);
            $table->string('category', 30);
            $table->date('transaction_date');
            $table->decimal('amount', 20, 2);
            $table->string('reference', 100)->nullable();
            $table->text('description')->nullable();
            $table->uuid('request_token');
            $table->string('status', 20)->default('posted');
            $table->timestamp('posted_at')->nullable();
            $table->timestamps();
            $table->unique(['company_id', 'voucher_no'], 'transaction_company_voucher_unique');
            $table->unique(['company_id', 'request_token'], 'transaction_company_request_unique');
            $table->index(['company_id', 'transaction_date', 'category']);
        });

        Schema::create('journal_entries', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('transaction_id')->unique()->constrained()->restrictOnDelete();
            $table->foreignId('posted_by')->constrained('users')->restrictOnDelete();
            $table->string('voucher_no', 50);
            $table->date('entry_date');
            $table->text('narration')->nullable();
            $table->string('status', 20)->default('posted');
            $table->timestamp('posted_at');
            $table->timestamps();
        });

        Schema::create('journal_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('journal_entry_id')->constrained()->cascadeOnDelete();
            $table->foreignId('chart_of_account_id')->constrained('chart_of_accounts')->restrictOnDelete();
            $table->foreignId('money_account_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('party_id')->nullable()->constrained()->restrictOnDelete();
            $table->unsignedSmallInteger('sequence');
            $table->string('description')->nullable();
            $table->decimal('debit', 20, 2)->default(0);
            $table->decimal('credit', 20, 2)->default(0);
            $table->timestamps();
            $table->index(['company_id', 'chart_of_account_id']);
            $table->index(['company_id', 'party_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('journal_lines');
        Schema::dropIfExists('journal_entries');
        Schema::dropIfExists('transactions');
    }
};
