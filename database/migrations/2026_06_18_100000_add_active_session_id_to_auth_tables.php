<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['users', 'landing_admin_users'] as $tableName) {
            if (! Schema::hasTable($tableName) || Schema::hasColumn($tableName, 'active_session_id')) {
                continue;
            }

            Schema::table($tableName, function (Blueprint $table): void {
                $table->string('active_session_id')->nullable()->after('remember_token');
            });
        }
    }

    public function down(): void
    {
        foreach (['users', 'landing_admin_users'] as $tableName) {
            if (! Schema::hasTable($tableName) || ! Schema::hasColumn($tableName, 'active_session_id')) {
                continue;
            }

            Schema::table($tableName, function (Blueprint $table): void {
                $table->dropColumn('active_session_id');
            });
        }
    }
};
