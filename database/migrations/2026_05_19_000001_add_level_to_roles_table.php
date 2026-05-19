<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('roles', function (Blueprint $table) {
            if (!Schema::hasColumn('roles', 'level')) {
                $table->unsignedSmallInteger('level')->default(999)->after('description')->index();
            }
        });

        $levels = [
            'Super Admin' => 1,
            'Admin' => 2,
            'Company Admin' => 2,
            'Finance Manager' => 3,
            'Approver' => 3,
            'Accountant' => 4,
            'Cashier' => 5,
            'Sales User' => 5,
            'Purchase User' => 5,
            'Inventory / Store User' => 5,
            'Branch User' => 5,
            'Auditor' => 6,
            'Management Viewer / Report Viewer' => 6,
            'Manager' => 6,
            'Data Entry' => 7,
            'Data Entry Operator' => 7,
            'Support Admin' => 8,
        ];

        foreach ($levels as $role => $level) {
            DB::table('roles')->where('name', $role)->update(['level' => $level]);
        }
    }

    public function down(): void
    {
        Schema::table('roles', function (Blueprint $table) {
            if (Schema::hasColumn('roles', 'level')) {
                $table->dropColumn('level');
            }
        });
    }
};
