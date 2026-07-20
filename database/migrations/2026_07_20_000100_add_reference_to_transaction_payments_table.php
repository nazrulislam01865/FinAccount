<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('transaction_payments') || Schema::hasColumn('transaction_payments', 'reference')) {
            return;
        }

        Schema::table('transaction_payments', function (Blueprint $table): void {
            $table->string('reference', 100)->nullable()->after('money_account_id');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('transaction_payments') || ! Schema::hasColumn('transaction_payments', 'reference')) {
            return;
        }

        Schema::table('transaction_payments', function (Blueprint $table): void {
            $table->dropColumn('reference');
        });
    }
};
