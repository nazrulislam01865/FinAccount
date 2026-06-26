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

        foreach (TransactionTypes::definitions() as $value => $definition) {
            $existing = DB::table('accounting_options')
                ->where('option_group', AccountingOption::GROUP_TRANSACTION_CATEGORY)
                ->where('value', $value)
                ->first();

            $existingMetadata = [];
            if ($existing && filled($existing->metadata)) {
                $decoded = json_decode((string) $existing->metadata, true);
                $existingMetadata = is_array($decoded) ? $decoded : [];
            }

            $metadata = array_merge($existingMetadata, [
                'action_label' => (string) ($definition['action_label'] ?? $definition['label'] ?? $value),
                'voucher_prefix' => (string) ($definition['voucher_prefix'] ?? ''),
                'money_label' => (string) ($definition['money_label'] ?? 'Cash / Bank / Mobile Account'),
                'party_label' => (string) ($definition['party_label'] ?? 'Party'),
                'party_type' => (string) ($definition['party_type'] ?? 'Any'),
                'allowed_settlements' => TransactionTypes::ALL_SETTLEMENTS,
                'default_settlements' => [TransactionTypes::CASH],
                'posting_types' => array_values((array) ($definition['posting_types'] ?? [])),
                'flow' => TransactionTypes::flow($value, $existingMetadata),
            ]);

            $values = [
                'label' => (string) ($definition['label'] ?? $value),
                'sort_order' => $existing?->sort_order ?? $sortOrder,
                'metadata' => json_encode($metadata, JSON_THROW_ON_ERROR),
                'is_active' => true,
                'updated_at' => $now,
            ];

            if ($existing) {
                DB::table('accounting_options')
                    ->where('id', $existing->id)
                    ->update($values);
            } else {
                DB::table('accounting_options')->insert([
                    'option_group' => AccountingOption::GROUP_TRANSACTION_CATEGORY,
                    'value' => $value,
                    ...$values,
                    'created_at' => $now,
                ]);
            }

            $sortOrder += 10;
        }

        foreach (TransactionTypes::settlementDefinitions() as $value => $definition) {
            $existing = DB::table('accounting_options')
                ->where('option_group', AccountingOption::GROUP_SETTLEMENT_TYPE)
                ->where('value', $value)
                ->first();

            $values = [
                'label' => (string) $definition['label'],
                'sort_order' => match ($value) {
                    TransactionTypes::CASH => 10,
                    TransactionTypes::CREDIT => 20,
                    TransactionTypes::PARTIAL => 30,
                    default => 100,
                },
                'metadata' => null,
                'is_active' => true,
                'updated_at' => $now,
            ];

            if ($existing) {
                DB::table('accounting_options')
                    ->where('id', $existing->id)
                    ->update($values);
            } else {
                DB::table('accounting_options')->insert([
                    'option_group' => AccountingOption::GROUP_SETTLEMENT_TYPE,
                    'value' => $value,
                    ...$values,
                    'created_at' => $now,
                ]);
            }
        }
    }

    public function down(): void
    {
        // Core transaction and payment types are system data and must not be removed.
    }
};
