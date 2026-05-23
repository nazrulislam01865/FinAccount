<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('report_configurations')) {
            Schema::create('report_configurations', function (Blueprint $table) {
                $table->id();
                $table->string('report_key', 100);
                $table->string('report_name', 150);
                $table->foreignId('role_id')->nullable()->constrained('roles')->nullOnDelete();
                $table->boolean('can_view')->default(true);
                $table->boolean('can_export')->default(true);
                $table->boolean('include_zero_balances')->default(false);
                $table->boolean('include_inactive_accounts')->default(false);
                $table->json('default_filters')->nullable();
                $table->unsignedSmallInteger('sort_order')->default(100);
                $table->string('status', 30)->default('Active');
                $table->timestamps();

                $table->index(['report_key', 'status'], 'report_config_key_status_idx');
                $table->index(['role_id', 'can_view', 'can_export'], 'report_config_role_access_idx');
            });
        }

        $this->seedDefaultReportConfigurations();
        $this->addIndexIfMissing('voucher_headers', 'vh_phase6_company_status_date_idx', ['company_id', 'status', 'voucher_date']);
        $this->addIndexIfMissing('voucher_details', 'vd_phase6_company_date_account_idx', ['company_id', 'transaction_date', 'account_id']);
        $this->addIndexIfMissing('voucher_details', 'vd_phase6_company_date_party_idx', ['company_id', 'transaction_date', 'party_id']);
        $this->addIndexIfMissing('chart_of_accounts', 'coa_phase6_company_type_status_idx', ['company_id', 'account_type_id', 'status']);
        $this->addIndexIfMissing('chart_of_accounts', 'coa_phase6_party_control_idx', ['company_id', 'is_party_control', 'party_type_id']);
    }

    public function down(): void
    {
        foreach ([
            'voucher_headers' => ['vh_phase6_company_status_date_idx'],
            'voucher_details' => ['vd_phase6_company_date_account_idx', 'vd_phase6_company_date_party_idx'],
            'chart_of_accounts' => ['coa_phase6_company_type_status_idx', 'coa_phase6_party_control_idx'],
        ] as $table => $indexes) {
            foreach ($indexes as $index) {
                $this->dropIndexIfExists($table, $index);
            }
        }

        Schema::dropIfExists('report_configurations');
    }

    private function seedDefaultReportConfigurations(): void
    {
        if (! Schema::hasTable('report_configurations')) {
            return;
        }

        $now = now();
        $rows = [
            ['transaction-list', 'Transaction List', 10],
            ['cash-bank-book', 'Cash / Bank Book', 20],
            ['trial-balance', 'Trial Balance', 30],
            ['income-statement', 'Income Statement', 40],
            ['balance-sheet', 'Balance Sheet', 50],
            ['cash-flow-statement', 'Cash Flow Statement', 60],
            ['customer-receivables', 'Customer Receivable', 70],
            ['supplier-payables', 'Supplier Payable', 80],
            ['sales-report', 'Sales Report', 90],
            ['expense-report', 'Expense Report', 100],
        ];

        foreach ($rows as [$key, $name, $sortOrder]) {
            DB::table('report_configurations')->updateOrInsert(
                ['report_key' => $key, 'role_id' => null],
                [
                    'report_name' => $name,
                    'can_view' => true,
                    'can_export' => true,
                    'include_zero_balances' => false,
                    'include_inactive_accounts' => false,
                    'default_filters' => json_encode(['basis' => 'Accrual'], JSON_THROW_ON_ERROR),
                    'sort_order' => $sortOrder,
                    'status' => 'Active',
                    'updated_at' => $now,
                    'created_at' => $now,
                ]
            );
        }
    }

    private function addIndexIfMissing(string $table, string $index, array $columns): void
    {
        if (! Schema::hasTable($table) || $this->indexExists($table, $index)) {
            return;
        }

        Schema::table($table, fn (Blueprint $blueprint) => $blueprint->index($columns, $index));
    }

    private function dropIndexIfExists(string $table, string $index): void
    {
        if (! Schema::hasTable($table) || ! $this->indexExists($table, $index)) {
            return;
        }

        Schema::table($table, fn (Blueprint $blueprint) => $blueprint->dropIndex($index));
    }

    private function indexExists(string $table, string $index): bool
    {
        try {
            $database = DB::getDatabaseName();
            return DB::table('information_schema.statistics')
                ->where('table_schema', $database)
                ->where('table_name', $table)
                ->where('index_name', $index)
                ->exists();
        } catch (Throwable) {
            return false;
        }
    }
};
