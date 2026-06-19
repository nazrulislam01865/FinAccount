<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->string('settlement_type', 20)->default('normal')->after('amount');
            $table->decimal('paid_amount', 20, 2)->nullable()->after('settlement_type');
            $table->decimal('due_amount', 20, 2)->nullable()->after('paid_amount');
            $table->date('due_date')->nullable()->after('due_amount');
            $table->index(['company_id', 'settlement_type'], 'transaction_company_settlement_idx');
        });
    }

    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropIndex('transaction_company_settlement_idx');
            $table->dropColumn(['settlement_type', 'paid_amount', 'due_amount', 'due_date']);
        });
    }
};
