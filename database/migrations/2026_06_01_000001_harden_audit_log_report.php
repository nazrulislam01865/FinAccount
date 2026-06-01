<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('audit_logs')) {
            Schema::create('audit_logs', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('company_id')->nullable()->constrained('companies')->nullOnDelete();
                $table->string('auditable_type');
                $table->unsignedBigInteger('auditable_id')->default(0);
                $table->string('module', 120)->nullable();
                $table->string('event', 120);
                $table->string('action', 120)->nullable();
                $table->json('old_values')->nullable();
                $table->json('new_values')->nullable();
                $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->string('ip_address', 64)->nullable();
                $table->text('user_agent')->nullable();
                $table->string('route_name', 160)->nullable();
                $table->string('request_method', 12)->nullable();
                $table->text('request_url')->nullable();
                $table->json('metadata')->nullable();
                $table->timestamp('created_at')->nullable();

                $table->index(['auditable_type', 'auditable_id'], 'audit_subject_idx');
            });
        } else {
            Schema::table('audit_logs', function (Blueprint $table): void {
                if (! Schema::hasColumn('audit_logs', 'company_id')) {
                    $table->foreignId('company_id')->nullable()->after('id')->constrained('companies')->nullOnDelete();
                }

                if (! Schema::hasColumn('audit_logs', 'auditable_id')) {
                    $table->unsignedBigInteger('auditable_id')->default(0)->after('auditable_type');
                }

                if (! Schema::hasColumn('audit_logs', 'module')) {
                    $table->string('module', 120)->nullable()->after('auditable_id');
                }

                if (! Schema::hasColumn('audit_logs', 'action')) {
                    $table->string('action', 120)->nullable()->after('event');
                }

                if (! Schema::hasColumn('audit_logs', 'ip_address')) {
                    $table->string('ip_address', 64)->nullable()->after('user_id');
                }

                if (! Schema::hasColumn('audit_logs', 'user_agent')) {
                    $table->text('user_agent')->nullable()->after('ip_address');
                }

                if (! Schema::hasColumn('audit_logs', 'route_name')) {
                    $table->string('route_name', 160)->nullable()->after('user_agent');
                }

                if (! Schema::hasColumn('audit_logs', 'request_method')) {
                    $table->string('request_method', 12)->nullable()->after('route_name');
                }

                if (! Schema::hasColumn('audit_logs', 'request_url')) {
                    $table->text('request_url')->nullable()->after('request_method');
                }

                if (! Schema::hasColumn('audit_logs', 'metadata')) {
                    $table->json('metadata')->nullable()->after('request_url');
                }
            });
        }

        DB::table('audit_logs')
            ->whereNull('action')
            ->orWhere('action', '')
            ->update(['action' => DB::raw('event')]);

        DB::table('audit_logs')
            ->whereNull('module')
            ->orWhere('module', '')
            ->update(['module' => DB::raw('auditable_type')]);

        $this->addIndexIfMissing('audit_logs', 'audit_company_created_idx', ['company_id', 'created_at']);
        $this->addIndexIfMissing('audit_logs', 'audit_user_created_idx', ['user_id', 'created_at']);
        $this->addIndexIfMissing('audit_logs', 'audit_module_action_created_idx', ['module', 'action', 'created_at']);
        $this->addIndexIfMissing('audit_logs', 'audit_route_created_idx', ['route_name', 'created_at']);
    }

    public function down(): void
    {
        foreach ([
            'audit_route_created_idx',
            'audit_module_action_created_idx',
            'audit_user_created_idx',
            'audit_company_created_idx',
        ] as $index) {
            $this->dropIndexIfExists('audit_logs', $index);
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
            foreach (DB::select('PRAGMA index_list(' . $quotedTable . ')') as $row) {
                if (($row->name ?? null) === $index) {
                    return true;
                }
            }
        }

        return false;
    }
};
