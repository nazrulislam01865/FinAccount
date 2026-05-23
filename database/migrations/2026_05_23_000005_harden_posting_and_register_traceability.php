<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->alignRegisterTraceability('due_register', 'due_register_voucher_detail_idx');
        $this->alignRegisterTraceability('advance_register', 'advance_register_voucher_detail_idx');
    }

    public function down(): void
    {
        $this->dropRegisterTraceability('advance_register', 'advance_register_voucher_detail_idx');
        $this->dropRegisterTraceability('due_register', 'due_register_voucher_detail_idx');
    }

    private function alignRegisterTraceability(string $tableName, string $indexName): void
    {
        if (! Schema::hasTable($tableName)) {
            return;
        }

        Schema::table($tableName, function (Blueprint $table) use ($tableName, $indexName) {
            if (! Schema::hasColumn($tableName, 'voucher_detail_id')) {
                $table->foreignId('voucher_detail_id')
                    ->nullable()
                    ->after('voucher_header_id')
                    ->constrained('voucher_details')
                    ->nullOnDelete();
            }

            if (! Schema::hasColumn($tableName, 'source_voucher_detail_id')) {
                $table->foreignId('source_voucher_detail_id')
                    ->nullable()
                    ->after('voucher_detail_id')
                    ->constrained('voucher_details')
                    ->nullOnDelete();
            }

            if (! $this->indexExists($tableName, $indexName)) {
                $table->index(['voucher_header_id', 'voucher_detail_id'], $indexName);
            }
        });
    }

    private function dropRegisterTraceability(string $tableName, string $indexName): void
    {
        if (! Schema::hasTable($tableName)) {
            return;
        }

        Schema::table($tableName, function (Blueprint $table) use ($tableName, $indexName) {
            if ($this->indexExists($tableName, $indexName)) {
                $table->dropIndex($indexName);
            }

            if (Schema::hasColumn($tableName, 'source_voucher_detail_id')) {
                $table->dropConstrainedForeignId('source_voucher_detail_id');
            }

            if (Schema::hasColumn($tableName, 'voucher_detail_id')) {
                $table->dropConstrainedForeignId('voucher_detail_id');
            }
        });
    }

    private function indexExists(string $tableName, string $indexName): bool
    {
        if (! Schema::hasTable($tableName)) {
            return false;
        }

        $connection = Schema::getConnection();
        $driver = $connection->getDriverName();

        try {
            if ($driver === 'mysql') {
                return DB::table('information_schema.statistics')
                    ->where('table_schema', $connection->getDatabaseName())
                    ->where('table_name', $tableName)
                    ->where('index_name', $indexName)
                    ->exists();
            }

            if ($driver === 'sqlite') {
                return collect(DB::select("PRAGMA index_list('{$tableName}')"))
                    ->contains(fn ($index): bool => ($index->name ?? null) === $indexName);
            }

            if ($driver === 'pgsql') {
                return DB::table('pg_indexes')
                    ->where('schemaname', 'public')
                    ->where('tablename', $tableName)
                    ->where('indexname', $indexName)
                    ->exists();
            }
        } catch (Throwable) {
            return false;
        }

        return false;
    }
};
