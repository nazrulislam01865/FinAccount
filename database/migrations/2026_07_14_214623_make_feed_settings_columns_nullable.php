<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('feed_settings', function (Blueprint $table) {
            $table->unsignedBigInteger('purchase_transaction_head_id')->nullable()->change();
            $table->unsignedBigInteger('sale_transaction_head_id')->nullable()->change();
            $table->unsignedBigInteger('cogs_account_id')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('feed_settings', function (Blueprint $table) {
            $table->unsignedBigInteger('purchase_transaction_head_id')->nullable(false)->change();
            $table->unsignedBigInteger('sale_transaction_head_id')->nullable(false)->change();
            $table->unsignedBigInteger('cogs_account_id')->nullable(false)->change();
        });
    }
};
