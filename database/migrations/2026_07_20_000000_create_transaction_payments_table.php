<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transaction_payments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('transaction_id')->constrained()->cascadeOnDelete();
            $table->foreignId('money_account_id')->constrained()->restrictOnDelete();
            $table->string('reference', 100)->nullable();
            $table->unsignedSmallInteger('sequence');
            $table->decimal('amount', 20, 2);
            $table->timestamps();

            $table->unique(['transaction_id', 'sequence'], 'transaction_payment_sequence_unique');
            $table->index(['company_id', 'money_account_id'], 'transaction_payment_money_lookup');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transaction_payments');
    }
};
