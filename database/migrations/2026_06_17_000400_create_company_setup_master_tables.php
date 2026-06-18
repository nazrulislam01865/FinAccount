<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('business_types', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('code', 30);
            $table->string('name', 120);
            $table->text('description')->nullable();
            $table->boolean('is_default')->default(false);
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
            $table->unique(['company_id', 'code']);
            $table->index(['company_id', 'is_active', 'sort_order']);
        });

        Schema::create('currencies', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('code', 3);
            $table->string('name', 100);
            $table->string('symbol', 12)->nullable();
            $table->unsignedTinyInteger('decimal_places')->default(2);
            $table->boolean('is_default')->default(false);
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
            $table->unique(['company_id', 'code']);
            $table->index(['company_id', 'is_active', 'sort_order']);
        });

        Schema::create('time_zones', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('code', 50);
            $table->string('name', 120);
            $table->string('utc_offset', 20);
            $table->string('php_timezone', 100);
            $table->boolean('is_default')->default(false);
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
            $table->unique(['company_id', 'code']);
            $table->unique(['company_id', 'php_timezone']);
            $table->index(['company_id', 'is_active', 'sort_order']);
        });

        Schema::create('financial_years', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('name', 100);
            $table->date('start_date');
            $table->date('end_date');
            $table->date('lock_date')->nullable();
            $table->boolean('is_active')->default(true);
            $table->boolean('is_current')->default(false);
            $table->string('status', 20)->default('open');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['company_id', 'name']);
            $table->index(['company_id', 'start_date', 'end_date'], 'financial_year_company_dates_idx');
            $table->index(['company_id', 'is_current', 'is_active'], 'financial_year_company_current_idx');
        });

        Schema::table('companies', function (Blueprint $table): void {
            $table->string('short_name', 120)->nullable()->after('name');
            $table->foreignId('business_type_id')->nullable()->after('short_name')->constrained('business_types')->nullOnDelete();
            $table->string('trade_license_no', 100)->nullable()->after('business_type_id');
            $table->string('bin_vat_registration_no', 100)->nullable()->after('trade_license_no');
            $table->string('tin', 100)->nullable()->after('bin_vat_registration_no');
            $table->foreignId('currency_id')->nullable()->after('currency_code')->constrained('currencies')->nullOnDelete();
            $table->string('accounting_method', 20)->default('accrual')->after('currency_id');
            $table->foreignId('time_zone_id')->nullable()->after('timezone')->constrained('time_zones')->nullOnDelete();
            $table->foreignId('default_financial_year_id')->nullable()->after('time_zone_id')->constrained('financial_years')->nullOnDelete();
            $table->string('default_branch', 150)->nullable()->after('default_financial_year_id');
            $table->text('address')->nullable()->after('default_branch');
            $table->string('contact_email')->nullable()->after('address');
            $table->string('contact_phone', 50)->nullable()->after('contact_email');
            $table->string('website')->nullable()->after('contact_phone');
            $table->string('logo_path')->nullable()->after('website');
            $table->string('favicon_path')->nullable()->after('logo_path');
            $table->timestamp('setup_completed_at')->nullable()->after('favicon_path');
            $table->foreignId('updated_by')->nullable()->after('setup_completed_at')->constrained('users')->nullOnDelete();
        });

        $now = now();
        $year = (int) $now->format('Y');

        DB::table('companies')->orderBy('id')->get()->each(function (object $company) use ($now, $year): void {
            $businessTypeId = DB::table('business_types')->insertGetId([
                'company_id' => $company->id,
                'code' => 'OTHER',
                'name' => 'Other Business',
                'description' => 'General business category. Add more business types from Other Master Data.',
                'is_default' => true,
                'is_active' => true,
                'sort_order' => 100,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            DB::table('business_types')->insert([
                [
                    'company_id' => $company->id,
                    'code' => 'TRADING',
                    'name' => 'Trading',
                    'description' => 'Buying and selling goods.',
                    'is_default' => false,
                    'is_active' => true,
                    'sort_order' => 10,
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
                [
                    'company_id' => $company->id,
                    'code' => 'SERVICE',
                    'name' => 'Service',
                    'description' => 'Professional or operational services.',
                    'is_default' => false,
                    'is_active' => true,
                    'sort_order' => 20,
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
                [
                    'company_id' => $company->id,
                    'code' => 'MANUFACTURING',
                    'name' => 'Manufacturing',
                    'description' => 'Production and manufacturing activities.',
                    'is_default' => false,
                    'is_active' => true,
                    'sort_order' => 30,
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
                [
                    'company_id' => $company->id,
                    'code' => 'AGRICULTURE',
                    'name' => 'Agriculture',
                    'description' => 'Farm, fisheries, livestock, and agriculture.',
                    'is_default' => false,
                    'is_active' => true,
                    'sort_order' => 40,
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
            ]);

            $currencyId = DB::table('currencies')->insertGetId([
                'company_id' => $company->id,
                'code' => $company->currency_code ?: 'BDT',
                'name' => ($company->currency_code ?: 'BDT') === 'BDT' ? 'Bangladeshi Taka' : ($company->currency_code ?: 'BDT'),
                'symbol' => ($company->currency_code ?: 'BDT') === 'BDT' ? '৳' : ($company->currency_code ?: 'BDT'),
                'decimal_places' => 2,
                'is_default' => true,
                'is_active' => true,
                'sort_order' => 10,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            $timeZoneId = DB::table('time_zones')->insertGetId([
                'company_id' => $company->id,
                'code' => 'ASIA_DHAKA',
                'name' => 'Dhaka',
                'utc_offset' => 'UTC+06:00',
                'php_timezone' => $company->timezone ?: 'Asia/Dhaka',
                'is_default' => true,
                'is_active' => true,
                'sort_order' => 10,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            $financialYearId = DB::table('financial_years')->insertGetId([
                'company_id' => $company->id,
                'name' => 'FY '.$year,
                'start_date' => $year.'-01-01',
                'end_date' => $year.'-12-31',
                'lock_date' => null,
                'is_active' => true,
                'is_current' => true,
                'status' => 'open',
                'created_by' => null,
                'updated_by' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            DB::table('companies')->where('id', $company->id)->update([
                'short_name' => mb_substr((string) $company->name, 0, 120),
                'business_type_id' => $businessTypeId,
                'currency_id' => $currencyId,
                'accounting_method' => 'accrual',
                'time_zone_id' => $timeZoneId,
                'default_financial_year_id' => $financialYearId,
                'setup_completed_at' => $now,
                'updated_at' => $now,
            ]);
        });
    }

    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('updated_by');
            $table->dropColumn('setup_completed_at');
            $table->dropColumn('favicon_path');
            $table->dropColumn('logo_path');
            $table->dropColumn('website');
            $table->dropColumn('contact_phone');
            $table->dropColumn('contact_email');
            $table->dropColumn('address');
            $table->dropColumn('default_branch');
            $table->dropConstrainedForeignId('default_financial_year_id');
            $table->dropConstrainedForeignId('time_zone_id');
            $table->dropColumn('accounting_method');
            $table->dropConstrainedForeignId('currency_id');
            $table->dropColumn('tin');
            $table->dropColumn('bin_vat_registration_no');
            $table->dropColumn('trade_license_no');
            $table->dropConstrainedForeignId('business_type_id');
            $table->dropColumn('short_name');
        });

        Schema::dropIfExists('financial_years');
        Schema::dropIfExists('time_zones');
        Schema::dropIfExists('currencies');
        Schema::dropIfExists('business_types');
    }
};
