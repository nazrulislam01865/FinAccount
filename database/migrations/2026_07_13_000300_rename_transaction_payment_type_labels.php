<?php

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

        foreach ($this->newLabels() as $value => [$label, $sortOrder]) {
            DB::table('accounting_options')
                ->where('option_group', 'settlement_type')
                ->where('value', $value)
                ->update([
                    'label' => $label,
                    'sort_order' => $sortOrder,
                    'updated_at' => $now,
                ]);
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('accounting_options')) {
            return;
        }

        $now = now();

        foreach ($this->oldLabels() as $value => [$label, $sortOrder]) {
            DB::table('accounting_options')
                ->where('option_group', 'settlement_type')
                ->where('value', $value)
                ->update([
                    'label' => $label,
                    'sort_order' => $sortOrder,
                    'updated_at' => $now,
                ]);
        }
    }

    /** @return array<string, array{string, int}> */
    private function newLabels(): array
    {
        return [
            'CASH' => ['Fully paid/received', 10],
            'PARTIAL' => ['Partially paid/received', 20],
            'CREDIT' => ['Fully due', 30],
        ];
    }

    /** @return array<string, array{string, int}> */
    private function oldLabels(): array
    {
        return [
            'CASH' => ['Paid/received in full', 10],
            'CREDIT' => ['Fully due', 20],
            'PARTIAL' => ['Part paid, remaining due', 30],
        ];
    }
};
