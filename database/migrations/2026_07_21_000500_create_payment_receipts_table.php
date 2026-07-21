<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('payment_receipts')) {
            Schema::create('payment_receipts', function (Blueprint $table): void {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->foreignId('company_id')->constrained()->cascadeOnDelete();
                $table->foreignId('transaction_id')->unique()->constrained()->cascadeOnDelete();
                $table->foreignId('party_id')->nullable()->constrained('parties')->nullOnDelete();
                $table->string('receipt_no', 80);
                $table->string('title', 120)->default('Payment Receipt');
                $table->date('receipt_date');
                $table->string('due_type', 30)->nullable();
                $table->decimal('amount', 20, 2)->default(0);
                $table->decimal('previous_due_amount', 20, 2)->nullable();
                $table->decimal('remaining_due_amount', 20, 2)->nullable();
                $table->string('status', 20)->default('issued');
                $table->json('party_snapshot')->nullable();
                $table->json('company_snapshot')->nullable();
                $table->timestamps();

                $table->unique(['company_id', 'receipt_no'], 'payment_receipt_company_no_unique');
                $table->index(['company_id', 'receipt_date'], 'payment_receipt_company_date_idx');
                $table->index(['company_id', 'due_type'], 'payment_receipt_company_due_type_idx');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_receipts');
    }
};
