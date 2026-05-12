<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('voucher_headers', function (Blueprint $table) {
            $table->id();

            $table->foreignId('company_id')
                ->nullable()
                ->constrained('companies')
                ->nullOnDelete();

            $table->foreignId('financial_year_id')
                ->constrained('financial_years')
                ->restrictOnDelete();

            $table->string('voucher_number')->unique();
            $table->string('voucher_type', 100);
            $table->date('voucher_date');

            $table->foreignId('transaction_head_id')
                ->constrained('transaction_heads')
                ->restrictOnDelete();

            $table->foreignId('settlement_type_id')
                ->constrained('settlement_types')
                ->restrictOnDelete();

            $table->foreignId('party_id')
                ->nullable()
                ->constrained('parties')
                ->restrictOnDelete();

            $table->foreignId('cash_bank_account_id')
                ->nullable()
                ->constrained('cash_bank_accounts')
                ->restrictOnDelete();

            $table->decimal('amount', 18, 2);
            $table->decimal('total_debit', 18, 2)->default(0);
            $table->decimal('total_credit', 18, 2)->default(0);

            $table->string('party_ledger_effect')->default('No Effect');
            $table->string('cash_bank_effect')->default('No Cash/Bank');

            $table->string('reference')->nullable();
            $table->text('notes')->nullable();

            $table->enum('status', [
                'Draft',
                'Pending Review',
                'Posted',
                'Cancelled',
                'Reversed',
            ])->default('Draft');

            $table->timestamp('posted_at')->nullable();

            $table->foreignId('created_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->foreignId('updated_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['voucher_date', 'status'], 'vh_date_status_idx');
            $table->index(['party_id', 'status'], 'vh_party_status_idx');
            $table->index(['financial_year_id', 'voucher_type'], 'vh_year_type_idx');
        });

        Schema::create('voucher_details', function (Blueprint $table) {
            $table->id();

            $table->foreignId('voucher_header_id')
                ->constrained('voucher_headers')
                ->cascadeOnDelete();

            $table->unsignedInteger('line_no')->default(1);

            $table->foreignId('account_id')
                ->constrained('chart_of_accounts')
                ->restrictOnDelete();

            $table->foreignId('party_id')
                ->nullable()
                ->constrained('parties')
                ->restrictOnDelete();

            $table->enum('entry_type', ['Debit', 'Credit']);
            $table->decimal('debit', 18, 2)->default(0);
            $table->decimal('credit', 18, 2)->default(0);
            $table->string('narration')->nullable();

            $table->timestamps();

            $table->index(['account_id', 'entry_type'], 'vd_account_type_idx');
            $table->index(['party_id', 'account_id'], 'vd_party_account_idx');
        });

        Schema::create('due_register', function (Blueprint $table) {
            $table->id();

            $table->foreignId('voucher_header_id')
                ->constrained('voucher_headers')
                ->cascadeOnDelete();

            $table->foreignId('party_id')
                ->constrained('parties')
                ->restrictOnDelete();

            $table->foreignId('account_id')
                ->constrained('chart_of_accounts')
                ->restrictOnDelete();

            $table->enum('due_type', ['Payable', 'Receivable']);
            $table->enum('movement', ['Increase', 'Decrease']);
            $table->decimal('amount', 18, 2);
            $table->decimal('balance_effect', 18, 2);

            $table->enum('status', [
                'Open',
                'Partially Paid',
                'Paid / Collected',
                'Overdue',
                'Cancelled',
            ])->default('Open');

            $table->date('due_date')->nullable();
            $table->timestamps();

            $table->index(['party_id', 'due_type', 'status'], 'due_party_type_status_idx');
        });

        Schema::create('advance_register', function (Blueprint $table) {
            $table->id();

            $table->foreignId('voucher_header_id')
                ->constrained('voucher_headers')
                ->cascadeOnDelete();

            $table->foreignId('party_id')
                ->constrained('parties')
                ->restrictOnDelete();

            $table->foreignId('account_id')
                ->constrained('chart_of_accounts')
                ->restrictOnDelete();

            $table->enum('advance_type', ['Paid', 'Received']);
            $table->enum('movement', ['Increase', 'Decrease']);
            $table->decimal('amount', 18, 2);
            $table->decimal('balance_effect', 18, 2);

            $table->enum('status', [
                'Open',
                'Partially Adjusted',
                'Fully Adjusted',
                'Cancelled',
            ])->default('Open');

            $table->timestamps();

            $table->index(['party_id', 'advance_type', 'status'], 'adv_party_type_status_idx');
        });

        Schema::create('voucher_attachments', function (Blueprint $table) {
            $table->id();

            $table->foreignId('voucher_header_id')
                ->constrained('voucher_headers')
                ->cascadeOnDelete();

            $table->string('original_name');
            $table->string('file_path');
            $table->string('mime_type')->nullable();
            $table->unsignedBigInteger('size_bytes')->default(0);

            $table->foreignId('created_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamps();
        });

        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();

            $table->string('auditable_type');
            $table->unsignedBigInteger('auditable_id');
            $table->string('event');
            $table->json('old_values')->nullable();
            $table->json('new_values')->nullable();

            $table->foreignId('user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamp('created_at')->nullable();

            $table->index(['auditable_type', 'auditable_id'], 'audit_subject_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
        Schema::dropIfExists('voucher_attachments');
        Schema::dropIfExists('advance_register');
        Schema::dropIfExists('due_register');
        Schema::dropIfExists('voucher_details');
        Schema::dropIfExists('voucher_headers');
    }
};
