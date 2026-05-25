<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('financial_years', function (Blueprint $table) {
            $table->id();

            $table->foreignId('company_id')
                ->nullable()
                ->constrained('companies')
                ->nullOnDelete();

            $table->string('name');
            $table->date('start_date');
            $table->date('end_date');
            $table->date('lock_date')->nullable();

            $table->boolean('is_active')->default(true);
            $table->boolean('is_current')->default(false);

            $table->string('status', 20)->default('Open');

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

            $table->index(['company_id', 'is_current'], 'fy_company_current_idx');
            $table->index(['company_id', 'status', 'start_date', 'end_date'], 'fy_company_status_dates_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('financial_years');
    }
};