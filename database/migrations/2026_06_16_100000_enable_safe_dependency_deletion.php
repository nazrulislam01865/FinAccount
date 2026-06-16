<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('money_accounts', function (Blueprint $table): void {
            $table->dropForeign(['chart_of_account_id']);
        });

        Schema::table('transaction_heads', function (Blueprint $table): void {
            $table->dropForeign(['accounting_rule_id']);
            $table->dropForeign(['posting_account_id']);
        });

        Schema::table('transactions', function (Blueprint $table): void {
            $table->dropForeign(['transaction_head_id']);
            $table->dropForeign(['money_account_id']);
            $table->dropForeign(['party_id']);
        });

        Schema::table('journal_lines', function (Blueprint $table): void {
            $table->dropForeign(['chart_of_account_id']);
            $table->dropForeign(['money_account_id']);
            $table->dropForeign(['party_id']);
        });

        Schema::table('money_accounts', function (Blueprint $table): void {
            $table->unsignedBigInteger('chart_of_account_id')->nullable()->change();
            $table->string('kind', 30)->nullable()->change();
            $table->foreign('chart_of_account_id')->references('id')->on('chart_of_accounts')->nullOnDelete();
        });

        Schema::table('parties', function (Blueprint $table): void {
            $table->string('type', 30)->nullable()->change();
        });

        Schema::table('accounting_rules', function (Blueprint $table): void {
            $table->string('category', 30)->nullable()->change();
            $table->string('party_type', 30)->nullable()->default(null)->change();
        });

        Schema::table('transaction_heads', function (Blueprint $table): void {
            $table->unsignedBigInteger('accounting_rule_id')->nullable()->change();
            $table->unsignedBigInteger('posting_account_id')->nullable()->change();
            $table->string('category', 30)->nullable()->change();
            $table->foreign('accounting_rule_id')->references('id')->on('accounting_rules')->nullOnDelete();
            $table->foreign('posting_account_id')->references('id')->on('chart_of_accounts')->nullOnDelete();
        });

        Schema::table('transactions', function (Blueprint $table): void {
            $table->unsignedBigInteger('transaction_head_id')->nullable()->change();
            $table->string('category', 30)->nullable()->change();
            $table->foreign('transaction_head_id')->references('id')->on('transaction_heads')->nullOnDelete();
            $table->foreign('money_account_id')->references('id')->on('money_accounts')->nullOnDelete();
            $table->foreign('party_id')->references('id')->on('parties')->nullOnDelete();
        });

        Schema::table('journal_lines', function (Blueprint $table): void {
            $table->unsignedBigInteger('chart_of_account_id')->nullable()->change();
            $table->foreign('chart_of_account_id')->references('id')->on('chart_of_accounts')->nullOnDelete();
            $table->foreign('money_account_id')->references('id')->on('money_accounts')->nullOnDelete();
            $table->foreign('party_id')->references('id')->on('parties')->nullOnDelete();
        });

        Schema::table('document_sequences', function (Blueprint $table): void {
            $table->string('category', 30)->nullable()->change();
            $table->boolean('is_active')->default(true)->after('padding');
        });
    }

    public function down(): void
    {
        Schema::table('money_accounts', function (Blueprint $table): void {
            $table->dropForeign(['chart_of_account_id']);
        });
        Schema::table('transaction_heads', function (Blueprint $table): void {
            $table->dropForeign(['accounting_rule_id']);
            $table->dropForeign(['posting_account_id']);
        });
        Schema::table('transactions', function (Blueprint $table): void {
            $table->dropForeign(['transaction_head_id']);
            $table->dropForeign(['money_account_id']);
            $table->dropForeign(['party_id']);
        });
        Schema::table('journal_lines', function (Blueprint $table): void {
            $table->dropForeign(['chart_of_account_id']);
            $table->dropForeign(['money_account_id']);
            $table->dropForeign(['party_id']);
        });

        Schema::table('money_accounts', function (Blueprint $table): void {
            $table->foreign('chart_of_account_id')->references('id')->on('chart_of_accounts')->restrictOnDelete();
        });
        Schema::table('transaction_heads', function (Blueprint $table): void {
            $table->foreign('accounting_rule_id')->references('id')->on('accounting_rules')->restrictOnDelete();
            $table->foreign('posting_account_id')->references('id')->on('chart_of_accounts')->restrictOnDelete();
        });
        Schema::table('transactions', function (Blueprint $table): void {
            $table->foreign('transaction_head_id')->references('id')->on('transaction_heads')->restrictOnDelete();
            $table->foreign('money_account_id')->references('id')->on('money_accounts')->restrictOnDelete();
            $table->foreign('party_id')->references('id')->on('parties')->restrictOnDelete();
        });
        Schema::table('journal_lines', function (Blueprint $table): void {
            $table->foreign('chart_of_account_id')->references('id')->on('chart_of_accounts')->restrictOnDelete();
            $table->foreign('money_account_id')->references('id')->on('money_accounts')->restrictOnDelete();
            $table->foreign('party_id')->references('id')->on('parties')->restrictOnDelete();
        });
        Schema::table('document_sequences', function (Blueprint $table): void {
            $table->dropColumn('is_active');
        });
    }
};
