<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('roles', function (Blueprint $table) {
            if (!Schema::hasColumn('roles', 'level')) {
                $table->unsignedSmallInteger('level')->default(99)->after('description');
            }

            if (!Schema::hasColumn('roles', 'is_protected')) {
                $table->boolean('is_protected')->default(false)->after('level');
            }
        });
    }

    public function down(): void
    {
        Schema::table('roles', function (Blueprint $table) {
            if (Schema::hasColumn('roles', 'is_protected')) {
                $table->dropColumn('is_protected');
            }

            if (Schema::hasColumn('roles', 'level')) {
                $table->dropColumn('level');
            }
        });
    }
};
