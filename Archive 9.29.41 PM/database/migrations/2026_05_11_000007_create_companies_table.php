<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('companies', function (Blueprint $table) {
            $table->id();

            $table->string('company_name');
            $table->string('short_name', 120);

            $table->foreignId('business_type_id')
                ->nullable()
                ->constrained('business_types')
                ->nullOnDelete();

            $table->string('trade_license_no', 100)->nullable();
            $table->string('tax_id_bin', 100)->nullable();

            $table->foreignId('currency_id')
                ->constrained('currencies')
                ->restrictOnDelete();

            $table->foreignId('time_zone_id')
                ->constrained('time_zones')
                ->restrictOnDelete();

            $table->date('financial_year_start');
            $table->date('financial_year_end');

            $table->text('address')->nullable();
            $table->string('contact_email')->nullable();
            $table->string('contact_phone', 50)->nullable();
            $table->string('website')->nullable();
            $table->string('logo_path')->nullable();

            $table->string('journal_voucher_prefix', 20)->default('JV');
            $table->string('payment_voucher_prefix', 20)->default('PV');
            $table->string('receipt_voucher_prefix', 20)->default('RV');

            $table->boolean('enable_multi_branch')->default(false);

            $table->foreignId('created_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->foreignId('updated_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('companies');
    }
};
