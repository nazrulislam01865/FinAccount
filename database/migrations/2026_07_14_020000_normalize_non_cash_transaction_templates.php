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

        $categories = [];
        $now = now();

        DB::table('accounting_options')
            ->where('option_group', AccountingOption::GROUP_TRANSACTION_CATEGORY)
            ->orderBy('id')
            ->get()
            ->each(function (object $option) use (&$categories, $now): void {
                $metadata = [];
                if (filled($option->metadata ?? null)) {
                    $decoded = json_decode((string) $option->metadata, true);
                    $metadata = is_array($decoded) ? $decoded : [];
                }

                $flow = TransactionTypes::flow((string) $option->value, $metadata);

                if (! in_array($flow, [TransactionTypes::FLOW_TRANSFER, TransactionTypes::FLOW_NON_CASH], true)) {
                    return;
                }

                $metadata['flow'] = $flow;
                $metadata['allowed_settlements'] = [TransactionTypes::CASH];
                $metadata['default_settlements'] = [TransactionTypes::CASH];
                $categories[] = (string) $option->value;

                DB::table('accounting_options')
                    ->where('id', $option->id)
                    ->update([
                        'metadata' => json_encode($metadata),
                        'updated_at' => $now,
                    ]);
            });

        if ($categories === [] || ! Schema::hasTable('transaction_heads') || ! Schema::hasColumn('transaction_heads', 'allowed_settlements')) {
            return;
        }

        DB::table('transaction_heads')
            ->whereIn('category', array_values(array_unique($categories)))
            ->update([
                'allowed_settlements' => json_encode([TransactionTypes::CASH]),
                'updated_at' => $now,
            ]);
    }

    public function down(): void
    {
        // This migration only narrows invalid setup data for Non-Cash/Transfer flows.
        // It is intentionally not reversed because widening old payment types can
        // recreate unsupported accounting-rule combinations.
    }
};
