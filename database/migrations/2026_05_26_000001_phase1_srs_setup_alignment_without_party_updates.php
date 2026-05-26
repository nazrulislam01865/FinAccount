<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            if (! Schema::hasColumn('companies', 'accounting_method')) {
                $table->string('accounting_method', 20)->default('Accrual')->after('currency_id');
            }

            if (! Schema::hasColumn('companies', 'default_financial_year_id')) {
                $table->foreignId('default_financial_year_id')
                    ->nullable()
                    ->after('financial_year_end')
                    ->constrained('financial_years')
                    ->nullOnDelete();
            }

            if (! Schema::hasColumn('companies', 'bin_vat_registration_no')) {
                $table->string('bin_vat_registration_no', 100)->nullable()->after('tax_id_bin');
            }

            if (! Schema::hasColumn('companies', 'tin')) {
                $table->string('tin', 100)->nullable()->after('bin_vat_registration_no');
            }

            if (! Schema::hasColumn('companies', 'status')) {
                $table->string('status', 20)->default('Active')->after('enable_multi_branch');
            }
        });

        if (Schema::hasTable('companies')) {
            DB::table('companies')
                ->whereNull('accounting_method')
                ->update(['accounting_method' => 'Accrual']);

            DB::table('companies')
                ->whereNull('status')
                ->update(['status' => 'Active']);

            if (Schema::hasColumn('companies', 'default_financial_year_id') && Schema::hasTable('financial_years')) {
                DB::table('companies')
                    ->whereNull('default_financial_year_id')
                    ->orderBy('id')
                    ->each(function ($company) {
                        $fyId = DB::table('financial_years')
                            ->where(function ($query) use ($company) {
                                $query->where('company_id', $company->id)
                                    ->orWhereNull('company_id');
                            })
                            ->where(function ($query) {
                                $query->where('is_current', true)
                                    ->orWhere('is_active', true);
                            })
                            ->orderByDesc('is_current')
                            ->orderByDesc('is_active')
                            ->orderByDesc('start_date')
                            ->value('id');

                        if ($fyId) {
                            DB::table('companies')->where('id', $company->id)->update([
                                'default_financial_year_id' => $fyId,
                            ]);
                        }
                    });
            }
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('companies', 'default_financial_year_id')) {
            $this->dropForeignIfExists('companies', 'companies_default_financial_year_id_foreign');
        }

        Schema::table('companies', function (Blueprint $table) {
            foreach ([
                'status',
                'tin',
                'bin_vat_registration_no',
                'default_financial_year_id',
                'accounting_method',
            ] as $column) {
                if (Schema::hasColumn('companies', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }

    private function dropForeignIfExists(string $table, string $foreign): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        $database = DB::getDatabaseName();
        $exists = DB::table('information_schema.TABLE_CONSTRAINTS')
            ->where('CONSTRAINT_SCHEMA', $database)
            ->where('TABLE_NAME', $table)
            ->where('CONSTRAINT_NAME', $foreign)
            ->where('CONSTRAINT_TYPE', 'FOREIGN KEY')
            ->exists();

        if ($exists) {
            Schema::table($table, fn (Blueprint $blueprint) => $blueprint->dropForeign($foreign));
        }
    }
};
