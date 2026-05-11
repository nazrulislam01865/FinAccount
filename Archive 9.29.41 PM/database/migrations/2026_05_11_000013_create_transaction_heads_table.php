<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('transaction_heads', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->enum('nature', ['Payment', 'Receipt', 'Due', 'Advance', 'Adjustment']);
            $table->foreignId('default_party_type_id')->nullable()->constrained('party_types')->nullOnDelete();
            $table->boolean('requires_party')->default(true);
            $table->boolean('requires_reference')->default(false);
            $table->text('description')->nullable();
            $table->enum('status', ['Active', 'Inactive'])->default('Active');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['company_id', 'name']);
        });

        Schema::create('settlement_type_transaction_head', function (Blueprint $table) {
            $table->id();
            $table->foreignId('transaction_head_id')->constrained()->cascadeOnDelete();
            $table->foreignId('settlement_type_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['transaction_head_id', 'settlement_type_id'], 'st_th_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('settlement_type_transaction_head');
        Schema::dropIfExists('transaction_heads');
    }
};
