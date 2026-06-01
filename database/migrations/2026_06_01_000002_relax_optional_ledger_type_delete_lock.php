<?php

use App\Models\LedgerType;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('ledger_types') || ! Schema::hasColumn('ledger_types', 'is_system')) {
            return;
        }

        DB::table('ledger_types')
            ->whereIn('code', LedgerType::PROTECTED_CODES)
            ->update([
                'is_system' => true,
                'updated_at' => now(),
            ]);

        DB::table('ledger_types')
            ->whereIn('code', ['INVENTORY', 'LOAN', 'EQUITY_CONTRA', 'OTHER'])
            ->update([
                'is_system' => false,
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        if (! Schema::hasTable('ledger_types') || ! Schema::hasColumn('ledger_types', 'is_system')) {
            return;
        }

        DB::table('ledger_types')
            ->whereIn('code', ['INVENTORY', 'LOAN', 'EQUITY_CONTRA', 'OTHER'])
            ->update([
                'is_system' => true,
                'updated_at' => now(),
            ]);
    }
};
