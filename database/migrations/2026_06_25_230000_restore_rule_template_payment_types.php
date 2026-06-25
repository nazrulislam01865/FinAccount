<?php

use App\Models\AccountingOption;
use App\Support\TransactionTypes;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('accounting_options')) {
            return;
        }

        $now = now();
        $sortOrder = 10;

        foreach (TransactionTypes::settlementDefinitions() as $value => $definition) {
            DB::table('accounting_options')->updateOrInsert(
                [
                    'option_group' => AccountingOption::GROUP_SETTLEMENT_TYPE,
                    'value' => $value,
                ],
                [
                    'label' => $definition['label'],
                    'sort_order' => $sortOrder,
                    'metadata' => null,
                    'is_active' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
            );

            $sortOrder += 10;
        }

        AccountingOption::query()
            ->where('option_group', AccountingOption::GROUP_TRANSACTION_CATEGORY)
            ->get()
            ->each(function (AccountingOption $option): void {
                $metadata = is_array($option->metadata) ? $option->metadata : [];
                $metadata['allowed_settlements'] = TransactionTypes::ALL_SETTLEMENTS;
                $metadata['default_settlements'] = $metadata['default_settlements'] ?? [TransactionTypes::CASH];

                $option->forceFill([
                    'metadata' => $metadata,
                    'updated_at' => now(),
                ])->save();
            });
    }

    public function down(): void
    {
        // This is a corrective data migration. Payment types intentionally remain available.
    }
};
