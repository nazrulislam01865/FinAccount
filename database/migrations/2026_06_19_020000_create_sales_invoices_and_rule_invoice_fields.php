<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('accounting_rules', function (Blueprint $table): void {
            if (! Schema::hasColumn('accounting_rules', 'generates_invoice')) {
                $table->boolean('generates_invoice')->default(false)->after('money_required');
            }

            if (! Schema::hasColumn('accounting_rules', 'invoice_title')) {
                $table->string('invoice_title', 120)->nullable()->after('generates_invoice');
            }
        });

        if (! Schema::hasTable('sales_invoices')) {
            Schema::create('sales_invoices', function (Blueprint $table): void {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->foreignId('company_id')->constrained()->cascadeOnDelete();
                $table->foreignId('transaction_id')->unique()->constrained()->cascadeOnDelete();
                $table->foreignId('party_id')->nullable()->constrained('parties')->nullOnDelete();
                $table->string('invoice_no', 80);
                $table->string('title', 120)->default('Sales Invoice');
                $table->date('invoice_date');
                $table->date('due_date')->nullable();
                $table->decimal('subtotal', 20, 2)->default(0);
                $table->decimal('discount_amount', 20, 2)->default(0);
                $table->decimal('tax_amount', 20, 2)->default(0);
                $table->decimal('total_amount', 20, 2)->default(0);
                $table->decimal('paid_amount', 20, 2)->default(0);
                $table->decimal('due_amount', 20, 2)->default(0);
                $table->string('status', 20)->default('issued');
                $table->json('customer_snapshot')->nullable();
                $table->json('company_snapshot')->nullable();
                $table->timestamps();

                $table->unique(['company_id', 'invoice_no'], 'sales_invoice_company_no_unique');
                $table->index(['company_id', 'invoice_date'], 'sales_invoice_company_date_idx');
                $table->index(['company_id', 'status'], 'sales_invoice_company_status_idx');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('sales_invoices');

        Schema::table('accounting_rules', function (Blueprint $table): void {
            if (Schema::hasColumn('accounting_rules', 'invoice_title')) {
                $table->dropColumn('invoice_title');
            }

            if (Schema::hasColumn('accounting_rules', 'generates_invoice')) {
                $table->dropColumn('generates_invoice');
            }
        });
    }
};
