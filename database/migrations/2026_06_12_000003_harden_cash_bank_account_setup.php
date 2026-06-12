<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('ledger_types')) {
            DB::table('ledger_types')->updateOrInsert(
                ['code' => 'MOBILE_WALLET'],
                [
                    'name' => 'Mobile Wallet',
                    'description' => 'Mobile financial service or digital wallet ledger used for receipts and payments.',
                    'is_system' => true,
                    'sort_order' => 35,
                    'status' => 'Active',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );
        }

        if (! Schema::hasTable('cash_bank_accounts')) {
            return;
        }

        $this->dropIndexIfExists('cash_bank_accounts', 'cash_bank_code_unique');
        $this->dropIndexIfExists('cash_bank_accounts', 'cash_bank_accounts_cash_bank_name_unique');
        $this->dropIndexIfExists('cash_bank_accounts', 'cash_bank_accounts_account_number_unique');

        Schema::table('cash_bank_accounts', function (Blueprint $table): void {
            $table->unique(
                ['company_id', 'cash_bank_code'],
                'cash_bank_company_code_unique'
            );

            $table->unique(
                ['company_id', 'cash_bank_name'],
                'cash_bank_company_name_unique'
            );

            $table->unique(
                ['company_id', 'account_number'],
                'cash_bank_company_account_number_unique'
            );
        });
    }

    public function down(): void
    {
        if (Schema::hasTable('cash_bank_accounts')) {
            $this->dropIndexIfExists('cash_bank_accounts', 'cash_bank_company_code_unique');
            $this->dropIndexIfExists('cash_bank_accounts', 'cash_bank_company_name_unique');
            $this->dropIndexIfExists('cash_bank_accounts', 'cash_bank_company_account_number_unique');
        }

        if (Schema::hasTable('ledger_types')) {
            DB::table('ledger_types')->where('code', 'MOBILE_WALLET')->delete();
        }
    }

    private function dropIndexIfExists(string $table, string $index): void
    {
        if (! $this->indexExists($table, $index)) {
            return;
        }

        Schema::table($table, function (Blueprint $blueprint) use ($index): void {
            $blueprint->dropUnique($index);
        });
    }

    private function indexExists(string $table, string $index): bool
    {
        try {
            return collect(Schema::getIndexes($table))
                ->contains(fn (array $definition): bool => ($definition['name'] ?? null) === $index);
        } catch (Throwable) {
            $driver = DB::getDriverName();

            if ($driver === 'mysql') {
                return DB::selectOne(
                    'SELECT COUNT(*) AS aggregate FROM information_schema.statistics WHERE table_schema = DATABASE() AND table_name = ? AND index_name = ?',
                    [$table, $index]
                )?->aggregate > 0;
            }

            return false;
        }
    }
};
