<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('transactions') || ! Schema::hasColumn('transactions', 'transaction_head_id')) {
            return;
        }

        // Transfer transactions are now posted directly from From account / To account
        // money-account COA mappings, so transaction_head_id must be optional.
        DB::statement('ALTER TABLE transactions MODIFY transaction_head_id BIGINT UNSIGNED NULL');

        $transferCategories = DB::table('accounting_options')
            ->where('option_group', 'transaction_category')
            ->get(['value', 'metadata'])
            ->filter(function ($row): bool {
                $metadata = json_decode((string) ($row->metadata ?? ''), true);
                $flow = strtolower(trim((string) ($metadata['flow'] ?? '')));
                $value = strtolower(trim((string) $row->value));

                return $flow === 'transfer' || str_contains($value, 'transfer');
            })
            ->pluck('value')
            ->filter()
            ->values()
            ->all();

        if ($transferCategories !== []) {
            DB::table('transactions')
                ->whereIn('category', $transferCategories)
                ->update(['transaction_head_id' => null]);
        }
    }

    public function down(): void
    {
        // This migration intentionally does not rebuild old transfer links. New
        // transfer posting no longer needs or stores a Transaction Head.
    }
};
