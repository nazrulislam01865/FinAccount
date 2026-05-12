<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('voucher_numbering_rules')) {
            return;
        }

        DB::statement("
            ALTER TABLE voucher_numbering_rules
            MODIFY voucher_type VARCHAR(100) NOT NULL
        ");

        DB::statement("
            ALTER TABLE voucher_numbering_rules
            MODIFY prefix VARCHAR(20) NOT NULL
        ");
    }

    public function down(): void
    {
        if (!Schema::hasTable('voucher_numbering_rules')) {
            return;
        }

        DB::statement("
            ALTER TABLE voucher_numbering_rules
            MODIFY voucher_type ENUM(
                'Payment Voucher',
                'Receipt Voucher',
                'Journal Voucher',
                'Contra / Transfer Voucher',
                'Draft Voucher'
            ) NOT NULL
        ");

        DB::statement("
            ALTER TABLE voucher_numbering_rules
            MODIFY prefix VARCHAR(10) NOT NULL
        ");
    }
};
