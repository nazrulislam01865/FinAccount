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

        DB::table('accounting_options')
            ->where('option_group', AccountingOption::GROUP_TRANSACTION_CATEGORY)
            ->whereIn('value', array_keys(TransactionTypes::definitions()))
            ->orderBy('id')
            ->get()
            ->each(function (object $option) use ($now): void {
                $metadata = json_decode((string) ($option->metadata ?? ''), true);
                $metadata = is_array($metadata) ? $metadata : [];
                $metadata['allowed_settlements'] = TransactionTypes::ALL_SETTLEMENTS;
                $metadata['default_settlements'] = [TransactionTypes::CASH];

                DB::table('accounting_options')
                    ->where('id', $option->id)
                    ->update([
                        'metadata' => json_encode($metadata),
                        'is_active' => true,
                        'updated_at' => $now,
                    ]);
            });

        if (Schema::hasTable('transaction_heads') && Schema::hasColumn('transaction_heads', 'allowed_settlements')) {
            DB::table('transaction_heads')
                ->whereIn('category', array_keys(TransactionTypes::definitions()))
                ->update([
                    'allowed_settlements' => json_encode(TransactionTypes::ALL_SETTLEMENTS),
                    'updated_at' => $now,
                ]);
        }
    }

    public function down(): void
    {
        // Corrective data migration: the previous incomplete cloud values are
        // unknown, so rolling back must not remove valid system payment types.
    }
};
