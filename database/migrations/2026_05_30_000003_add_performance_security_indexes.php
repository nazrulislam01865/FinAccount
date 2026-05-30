<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->addIndexIfMissing('users', 'perf_users_status_idx', ['status']);
        $this->addIndexIfMissing('landing_admin_users', 'perf_landing_admin_status_idx', ['status']);
        $this->addIndexIfMissing('landing_page_inquiries', 'perf_landing_inquiry_status_created_idx', ['status', 'created_at']);
        $this->addIndexIfMissing('landing_page_inquiries', 'perf_landing_inquiry_email_idx', ['email']);

        $this->addIndexIfMissing('audit_logs', 'perf_audit_company_created_idx', ['company_id', 'created_at']);
        $this->addIndexIfMissing('audit_logs', 'perf_audit_user_created_idx', ['user_id', 'created_at']);
        $this->addIndexIfMissing('audit_logs', 'perf_audit_module_action_idx', ['module', 'action']);

        $this->addIndexIfMissing('chart_of_accounts', 'perf_coa_parent_status_idx', ['parent_id', 'status']);
        $this->addIndexIfMissing('chart_of_accounts', 'perf_coa_code_status_idx', ['account_code', 'status']);
        $this->addIndexIfMissing('parties', 'perf_parties_type_status_name_idx', ['party_type_id', 'status', 'party_name']);
        $this->addIndexIfMissing('transaction_heads', 'perf_heads_status_name_idx', ['status', 'name']);
    }

    public function down(): void
    {
        foreach ([
            'users' => ['perf_users_status_idx'],
            'landing_admin_users' => ['perf_landing_admin_status_idx'],
            'landing_page_inquiries' => ['perf_landing_inquiry_status_created_idx', 'perf_landing_inquiry_email_idx'],
            'audit_logs' => ['perf_audit_company_created_idx', 'perf_audit_user_created_idx', 'perf_audit_module_action_idx'],
            'chart_of_accounts' => ['perf_coa_parent_status_idx', 'perf_coa_code_status_idx'],
            'parties' => ['perf_parties_type_status_name_idx'],
            'transaction_heads' => ['perf_heads_status_name_idx'],
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

        Schema::table($table, function (Blueprint $blueprint) use ($columns, $index): void {
            $blueprint->index($columns, $index);
        });
    }

    private function dropIndexIfExists(string $table, string $index): void
    {
        if (! Schema::hasTable($table) || ! $this->hasIndex($table, $index)) {
            return;
        }

        Schema::table($table, function (Blueprint $blueprint) use ($index): void {
            $blueprint->dropIndex($index);
        });
    }

    private function hasIndex(string $table, string $index): bool
    {
        $driver = DB::getDriverName();

        if ($driver === 'mysql' || $driver === 'mariadb') {
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
            $quotedTable = '"' . str_replace('"', '""', $table) . '"';
            $indexes = DB::select('PRAGMA index_list(' . $quotedTable . ')');

            foreach ($indexes as $row) {
                if (($row->name ?? null) === $index) {
                    return true;
                }
            }
        }

        return false;
    }
};
