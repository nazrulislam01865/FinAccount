<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('transactions') || Schema::hasColumn('transactions', 'transfer_to_money_account_id')) {
            return;
        }

        Schema::table('transactions', function (Blueprint $table): void {
            $table->foreignId('transfer_to_money_account_id')
                ->nullable()
                ->after('money_account_id')
                ->constrained('money_accounts')
                ->restrictOnDelete();
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('transactions') || ! Schema::hasColumn('transactions', 'transfer_to_money_account_id')) {
            return;
        }

        Schema::table('transactions', function (Blueprint $table): void {
            $table->dropForeign(['transfer_to_money_account_id']);
            $table->dropColumn('transfer_to_money_account_id');
        });
    }
};
