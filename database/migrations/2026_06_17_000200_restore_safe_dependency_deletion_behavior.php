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
            $table->foreign('chart_of_account_id')->references('id')->on('chart_of_accounts')->nullOnDelete();
        });

        Schema::table('parties', function (Blueprint $table): void {
            $table->dropForeign(['receivable_account_id']);
            $table->dropForeign(['payable_account_id']);
            $table->foreign('receivable_account_id')->references('id')->on('chart_of_accounts')->nullOnDelete();
            $table->foreign('payable_account_id')->references('id')->on('chart_of_accounts')->nullOnDelete();
        });

        Schema::table('transaction_heads', function (Blueprint $table): void {
            $table->dropForeign(['accounting_rule_id']);
            $table->dropForeign(['posting_account_id']);
            $table->foreign('accounting_rule_id')->references('id')->on('accounting_rules')->nullOnDelete();
            $table->foreign('posting_account_id')->references('id')->on('chart_of_accounts')->nullOnDelete();
        });

        Schema::table('transactions', function (Blueprint $table): void {
            $table->dropForeign(['transaction_head_id']);
            $table->dropForeign(['money_account_id']);
            $table->dropForeign(['party_id']);
            $table->foreign('transaction_head_id')->references('id')->on('transaction_heads')->nullOnDelete();
            $table->foreign('money_account_id')->references('id')->on('money_accounts')->nullOnDelete();
            $table->foreign('party_id')->references('id')->on('parties')->nullOnDelete();
        });

        Schema::table('journal_lines', function (Blueprint $table): void {
            $table->dropForeign(['chart_of_account_id']);
            $table->dropForeign(['money_account_id']);
            $table->dropForeign(['party_id']);
            $table->foreign('chart_of_account_id')->references('id')->on('chart_of_accounts')->nullOnDelete();
            $table->foreign('money_account_id')->references('id')->on('money_accounts')->nullOnDelete();
            $table->foreign('party_id')->references('id')->on('parties')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('money_accounts', function (Blueprint $table): void {
            $table->dropForeign(['chart_of_account_id']);
            $table->foreign('chart_of_account_id')->references('id')->on('chart_of_accounts')->restrictOnDelete();
        });

        Schema::table('parties', function (Blueprint $table): void {
            $table->dropForeign(['receivable_account_id']);
            $table->dropForeign(['payable_account_id']);
            $table->foreign('receivable_account_id')->references('id')->on('chart_of_accounts')->restrictOnDelete();
            $table->foreign('payable_account_id')->references('id')->on('chart_of_accounts')->restrictOnDelete();
        });

        Schema::table('transaction_heads', function (Blueprint $table): void {
            $table->dropForeign(['accounting_rule_id']);
            $table->dropForeign(['posting_account_id']);
            $table->foreign('accounting_rule_id')->references('id')->on('accounting_rules')->restrictOnDelete();
            $table->foreign('posting_account_id')->references('id')->on('chart_of_accounts')->restrictOnDelete();
        });

        Schema::table('transactions', function (Blueprint $table): void {
            $table->dropForeign(['transaction_head_id']);
            $table->dropForeign(['money_account_id']);
            $table->dropForeign(['party_id']);
            $table->foreign('transaction_head_id')->references('id')->on('transaction_heads')->restrictOnDelete();
            $table->foreign('money_account_id')->references('id')->on('money_accounts')->restrictOnDelete();
            $table->foreign('party_id')->references('id')->on('parties')->restrictOnDelete();
        });

        Schema::table('journal_lines', function (Blueprint $table): void {
            $table->dropForeign(['chart_of_account_id']);
            $table->dropForeign(['money_account_id']);
            $table->dropForeign(['party_id']);
            $table->foreign('chart_of_account_id')->references('id')->on('chart_of_accounts')->restrictOnDelete();
            $table->foreign('money_account_id')->references('id')->on('money_accounts')->restrictOnDelete();
            $table->foreign('party_id')->references('id')->on('parties')->restrictOnDelete();
        });
    }
};
