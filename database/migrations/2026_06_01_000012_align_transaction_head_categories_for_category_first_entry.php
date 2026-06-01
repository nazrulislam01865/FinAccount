<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('transaction_heads') || ! Schema::hasColumn('transaction_heads', 'category')) {
            return;
        }

        $this->applyCategory('Opening', ['Opening', 'Opening Balance'], ['opening']);
        $this->applyCategory('Employee', ['Employee'], ['employee', 'salary']);
        $this->applyCategory('Loan', ['Loan'], ['loan']);
        $this->applyCategory('Owner / Equity', ['Equity', 'Owner / Equity'], ['owner', 'equity', 'capital', 'withdrawal', 'drawing']);
        $this->applyCategory('Asset', ['Asset', 'Asset Purchase'], ['asset']);
        $this->applyCategory('Banking', ['Banking'], ['bank', 'transfer', 'charge', 'interest']);
        $this->applyCategory('Income', ['Income'], ['income', 'service']);
        $this->applyCategory('Expense', ['Expense', 'Expense Payment'], ['expense', 'rent', 'utility', 'office']);
        $this->applyCategory('Purchase', ['Purchase', 'Due'], ['purchase', 'supplier due', 'payable']);
        $this->applyCategory('Sales', ['Sales'], ['sales', 'sale', 'customer due', 'receivable']);
        $this->applyCategory('Receipt', ['Receipt', 'Advance'], ['receipt', 'collection', 'received']);
        $this->applyCategory('Payment', ['Payment'], ['payment', 'paid']);
        $this->applyCategory('Adjustment', ['Adjustment', 'Other', 'Journal'], ['adjust', 'journal', 'other']);

        DB::table('transaction_heads')
            ->whereNull('category')
            ->orWhere('category', '')
            ->update(['category' => 'Payment', 'nature' => 'Payment']);
    }

    public function down(): void
    {
        // Data normalisation is intentionally not reversed.
    }

    private function applyCategory(string $category, array $oldCategories, array $nameNeedles): void
    {
        $nature = match ($category) {
            'Sales', 'Receipt', 'Income' => 'Receipt',
            'Purchase' => 'Purchase',
            'Expense', 'Payment', 'Employee' => 'Payment',
            'Banking', 'Opening', 'Adjustment' => 'Adjustment',
            'Owner / Equity' => 'Equity',
            'Asset' => 'Asset',
            'Loan' => 'Loan',
            default => 'Payment',
        };

        DB::table('transaction_heads')
            ->where(function ($query) use ($oldCategories, $nameNeedles) {
                $query->whereIn('category', $oldCategories);

                foreach ($nameNeedles as $needle) {
                    $query->orWhere('name', 'like', '%' . $needle . '%');
                }
            })
            ->update([
                'category' => $category,
                'nature' => $nature,
            ]);
    }
};
