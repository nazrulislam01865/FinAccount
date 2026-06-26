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
        DB::transaction(function (): void {
            foreach (['accounting_rules', 'transaction_heads', 'transactions'] as $table) {
                $this->normalizeCategoryColumn($table);
            }

            $this->normalizeDocumentSequences();
            $this->normalizeTransactionCategoryOptions();
            $this->ensureCoreTransactionCategoryOptions();
        });
    }

    public function down(): void
    {
        // Canonical system values are data repair and must not be reverted.
    }

    private function normalizeCategoryColumn(string $table): void
    {
        if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'category')) {
            return;
        }

        DB::table($table)
            ->select(['id', 'category'])
            ->orderBy('id')
            ->chunkById(200, function ($rows) use ($table): void {
                foreach ($rows as $row) {
                    $current = trim((string) $row->category);
                    $canonical = TransactionTypes::normalize($current);

                    if ($canonical === $current || ! array_key_exists($canonical, TransactionTypes::definitions())) {
                        continue;
                    }

                    DB::table($table)
                        ->where('id', $row->id)
                        ->update(['category' => $canonical]);
                }
            });
    }

    private function normalizeDocumentSequences(): void
    {
        if (! Schema::hasTable('document_sequences') || ! Schema::hasColumn('document_sequences', 'category')) {
            return;
        }

        $rows = DB::table('document_sequences')->orderBy('id')->get();

        foreach ($rows as $row) {
            $current = trim((string) $row->category);
            $canonical = TransactionTypes::normalize($current);

            if ($canonical === $current || ! array_key_exists($canonical, TransactionTypes::definitions())) {
                continue;
            }

            $target = DB::table('document_sequences')
                ->where('company_id', $row->company_id)
                ->where('id', '!=', $row->id)
                ->get()
                ->first(fn ($candidate): bool => TransactionTypes::normalize((string) $candidate->category) === $canonical);

            if ($target) {
                DB::table('document_sequences')
                    ->where('id', $target->id)
                    ->update([
                        'category' => $canonical,
                        'next_number' => max((int) $target->next_number, (int) $row->next_number),
                        'padding' => max((int) $target->padding, (int) $row->padding),
                        'updated_at' => now(),
                    ]);

                DB::table('document_sequences')->where('id', $row->id)->delete();
                continue;
            }

            DB::table('document_sequences')
                ->where('id', $row->id)
                ->update(['category' => $canonical, 'updated_at' => now()]);
        }
    }

    private function normalizeTransactionCategoryOptions(): void
    {
        if (! Schema::hasTable('accounting_options')) {
            return;
        }

        $rows = DB::table('accounting_options')
            ->where('option_group', AccountingOption::GROUP_TRANSACTION_CATEGORY)
            ->orderBy('id')
            ->get();

        foreach ($rows as $row) {
            $current = trim((string) $row->value);
            $canonical = TransactionTypes::normalize($current);

            if ($canonical === $current || ! array_key_exists($canonical, TransactionTypes::definitions())) {
                continue;
            }

            $target = DB::table('accounting_options')
                ->where('option_group', AccountingOption::GROUP_TRANSACTION_CATEGORY)
                ->where('id', '!=', $row->id)
                ->get()
                ->first(fn ($candidate): bool => TransactionTypes::normalize((string) $candidate->value) === $canonical);

            if ($target) {
                $sourceMetadata = $this->decodeMetadata($row->metadata ?? null);
                $targetMetadata = $this->decodeMetadata($target->metadata ?? null);

                DB::table('accounting_options')
                    ->where('id', $target->id)
                    ->update([
                        'value' => $canonical,
                        'metadata' => json_encode(array_merge($sourceMetadata, $targetMetadata), JSON_THROW_ON_ERROR),
                        'is_active' => (bool) $target->is_active || (bool) $row->is_active,
                        'sort_order' => min((int) $target->sort_order, (int) $row->sort_order),
                        'updated_at' => now(),
                    ]);

                DB::table('accounting_options')->where('id', $row->id)->delete();
                continue;
            }

            DB::table('accounting_options')
                ->where('id', $row->id)
                ->update(['value' => $canonical, 'updated_at' => now()]);
        }
    }

    private function ensureCoreTransactionCategoryOptions(): void
    {
        if (! Schema::hasTable('accounting_options')) {
            return;
        }

        $sortOrder = 10;
        $now = now();

        foreach (TransactionTypes::definitions() as $value => $definition) {
            $row = DB::table('accounting_options')
                ->where('option_group', AccountingOption::GROUP_TRANSACTION_CATEGORY)
                ->get()
                ->first(fn ($candidate): bool => TransactionTypes::normalize((string) $candidate->value) === $value);

            $existingMetadata = $this->decodeMetadata($row->metadata ?? null);
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
                'value' => $value,
                'label' => (string) ($definition['label'] ?? $value),
                'sort_order' => $row?->sort_order ?? $sortOrder,
                'metadata' => json_encode($metadata, JSON_THROW_ON_ERROR),
                'is_active' => true,
                'updated_at' => $now,
            ];

            if ($row) {
                DB::table('accounting_options')->where('id', $row->id)->update($values);
            } else {
                DB::table('accounting_options')->insert([
                    'option_group' => AccountingOption::GROUP_TRANSACTION_CATEGORY,
                    ...$values,
                    'created_at' => $now,
                ]);
            }

            $sortOrder += 10;
        }
    }

    /** @return array<string, mixed> */
    private function decodeMetadata(mixed $metadata): array
    {
        if (! filled($metadata)) {
            return [];
        }

        if (is_array($metadata)) {
            return $metadata;
        }

        $decoded = json_decode((string) $metadata, true);

        return is_array($decoded) ? $decoded : [];
    }
};
