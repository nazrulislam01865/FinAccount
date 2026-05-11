<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('parties', function (Blueprint $table) {
            $table->id();

            $table->foreignId('company_id')
                ->nullable()
                ->constrained('companies')
                ->nullOnDelete();

            $table->string('party_code', 20)->unique();
            $table->string('party_name');

            $table->foreignId('party_type_id')
                ->constrained('party_types')
                ->restrictOnDelete();

            $table->string('mobile', 20)->nullable()->unique();
            $table->string('email')->nullable()->unique();
            $table->text('address')->nullable();

            $table->foreignId('linked_ledger_account_id')
                ->constrained('chart_of_accounts')
                ->restrictOnDelete();

            $table->decimal('opening_balance', 15, 2)->default(0);
            $table->enum('opening_balance_type', ['Debit', 'Credit'])->nullable();

            $table->text('notes')->nullable();

            $table->enum('status', ['Active', 'Inactive'])->default('Active');

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
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('parties');
    }
};
