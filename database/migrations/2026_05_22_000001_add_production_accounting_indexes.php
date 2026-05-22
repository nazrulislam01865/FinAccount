<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->addIndexIfMissing('voucher_headers', 'prod_vh_status_date_idx', ['status', 'voucher_date']);
        $this->addIndexIfMissing('voucher_headers', 'prod_vh_year_status_date_idx', ['financial_year_id', 'status', 'voucher_date']);
        $this->addIndexIfMissing('voucher_headers', 'prod_vh_head_settlement_idx', ['transaction_head_id', 'settlement_type_id']);

        $this->addIndexIfMissing('voucher_details', 'prod_vd_voucher_account_idx', ['voucher_header_id', 'account_id']);
        $this->addIndexIfMissing('voucher_details', 'prod_vd_account_voucher_idx', ['account_id', 'voucher_header_id']);
        $this->addIndexIfMissing('voucher_details', 'prod_vd_party_account_voucher_idx', ['party_id', 'account_id', 'voucher_header_id']);

        $this->addIndexIfMissing('cash_bank_accounts', 'prod_cb_linked_ledger_idx', ['linked_ledger_account_id']);
        $this->addIndexIfMissing('chart_of_accounts', 'prod_coa_type_posting_status_idx', ['account_type_id', 'posting_allowed', 'status']);

        $this->addIndexIfMissing('due_register', 'prod_due_voucher_account_idx', ['voucher_header_id', 'account_id']);
        $this->addIndexIfMissing('advance_register', 'prod_adv_voucher_account_idx', ['voucher_header_id', 'account_id']);
    }

    public function down(): void
    {
        foreach ([
            'voucher_headers' => ['prod_vh_status_date_idx', 'prod_vh_year_status_date_idx', 'prod_vh_head_settlement_idx'],
            'voucher_details' => ['prod_vd_voucher_account_idx', 'prod_vd_account_voucher_idx', 'prod_vd_party_account_voucher_idx'],
            'cash_bank_accounts' => ['prod_cb_linked_ledger_idx'],
            'chart_of_accounts' => ['prod_coa_type_posting_status_idx'],
            'due_register' => ['prod_due_voucher_account_idx'],
            'advance_register' => ['prod_adv_voucher_account_idx'],
        ] as $table => $indexes) {
            foreach ($indexes as $index) {
                $this->dropIndexIfExists($table, $index);
            }
        }
    }

    private function addIndexIfMissing(string $table, string $index, array $columns): void
    {
        if (! Schema::hasTable($table) || $this->hasIndex($table, $index)) {
            return;
        }

        foreach ($columns as $column) {
            if (! Schema::hasColumn($table, $column)) {
                return;
            }
        }

        Schema::table($table, function (Blueprint $blueprint) use ($columns, $index) {
            $blueprint->index($columns, $index);
        });
    }

    private function dropIndexIfExists(string $table, string $index): void
    {
        if (! Schema::hasTable($table) || ! $this->hasIndex($table, $index)) {
            return;
        }

        Schema::table($table, function (Blueprint $blueprint) use ($index) {
            $blueprint->dropIndex($index);
        });
    }

    private function hasIndex(string $table, string $index): bool
    {
        $driver = DB::getDriverName();

        if ($driver === 'mysql') {
            return DB::table('information_schema.statistics')
                ->whereRaw('table_schema = DATABASE()')
                ->where('table_name', $table)
                ->where('index_name', $index)
                ->exists();
        }

        if ($driver === 'pgsql') {
            return DB::table('pg_indexes')
                ->where('schemaname', 'public')
                ->where('tablename', $table)
                ->where('indexname', $index)
                ->exists();
        }

        if ($driver === 'sqlite') {
            $indexes = DB::select('PRAGMA index_list(' . str_replace('"', '""', $table) . ')');
            foreach ($indexes as $row) {
                if (($row->name ?? null) === $index) {
                    return true;
                }
            }
        }

        return false;
    }
};
