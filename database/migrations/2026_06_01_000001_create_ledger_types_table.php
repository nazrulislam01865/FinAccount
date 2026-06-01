<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('ledger_types')) {
            Schema::create('ledger_types', function (Blueprint $table) {
                $table->id();
                $table->string('name')->unique();
                $table->string('code')->unique();
                $table->text('description')->nullable();
                $table->boolean('is_system')->default(false);
                $table->unsignedInteger('sort_order')->default(0);
                $table->string('status', 20)->default('Active')->index();
                $table->timestamps();
            });
        }

        $now = now();
        $defaults = [
            ['name' => 'Group', 'code' => 'GROUP', 'description' => 'Structure-only account used for CoA grouping.', 'is_system' => true, 'sort_order' => 10],
            ['name' => 'Cash', 'code' => 'CASH', 'description' => 'Cash ledger selected in payment/receipt entry.', 'is_system' => true, 'sort_order' => 20],
            ['name' => 'Bank', 'code' => 'BANK', 'description' => 'Bank ledger selected in payment/receipt entry.', 'is_system' => true, 'sort_order' => 30],
            ['name' => 'Party Control', 'code' => 'PARTY_CONTROL', 'description' => 'Control ledger for customer, supplier, employee, owner or other party balances.', 'is_system' => true, 'sort_order' => 40],
            ['name' => 'Inventory', 'code' => 'INVENTORY', 'description' => 'Inventory or stock value ledger.', 'is_system' => false, 'sort_order' => 50],
            ['name' => 'Asset', 'code' => 'ASSET', 'description' => 'General asset ledger.', 'is_system' => true, 'sort_order' => 60],
            ['name' => 'Loan', 'code' => 'LOAN', 'description' => 'Loan receivable/payable ledger type.', 'is_system' => false, 'sort_order' => 70],
            ['name' => 'Liability', 'code' => 'LIABILITY', 'description' => 'General liability ledger.', 'is_system' => true, 'sort_order' => 80],
            ['name' => 'Equity', 'code' => 'EQUITY', 'description' => 'Capital/equity ledger.', 'is_system' => true, 'sort_order' => 90],
            ['name' => 'Equity Contra', 'code' => 'EQUITY_CONTRA', 'description' => 'Drawings or contra-equity ledger.', 'is_system' => false, 'sort_order' => 100],
            ['name' => 'Income', 'code' => 'INCOME', 'description' => 'Income/revenue ledger.', 'is_system' => true, 'sort_order' => 110],
            ['name' => 'Expense', 'code' => 'EXPENSE', 'description' => 'Expense/cost ledger.', 'is_system' => true, 'sort_order' => 120],
            ['name' => 'Other', 'code' => 'OTHER', 'description' => 'Other posting ledger type.', 'is_system' => false, 'sort_order' => 130],
        ];

        foreach ($defaults as $row) {
            DB::table('ledger_types')->updateOrInsert(
                ['name' => $row['name']],
                array_merge($row, ['status' => 'Active', 'created_at' => $now, 'updated_at' => $now])
            );
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('ledger_types');
    }
};
