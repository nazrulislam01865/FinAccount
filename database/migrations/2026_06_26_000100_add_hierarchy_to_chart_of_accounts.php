<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('chart_of_accounts', function (Blueprint $table) {
            $table->foreignId('parent_id')
                ->nullable()
                ->after('company_id')
                ->constrained('chart_of_accounts')
                ->restrictOnDelete();
            $table->unsignedTinyInteger('level')->default(3)->after('parent_id');
            $table->index(['company_id', 'level', 'parent_id'], 'coa_company_level_parent_idx');
        });

        // Existing flat COA rows are real posting ledgers. Preserve them as
        // unassigned Level 3 records so no existing transaction mapping breaks.
        DB::table('chart_of_accounts')->update(['level' => 3]);
    }

    public function down(): void
    {
        Schema::table('chart_of_accounts', function (Blueprint $table) {
            $table->dropForeign(['parent_id']);
            $table->dropIndex('coa_company_level_parent_idx');
            $table->dropColumn(['parent_id', 'level']);
        });
    }
};
