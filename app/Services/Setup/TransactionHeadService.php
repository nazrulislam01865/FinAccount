<?php

namespace App\Services\Setup;

use App\Models\Company;
use App\Models\TransactionHead;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class TransactionHeadService
{
    public function create(
        array $data,
        ?int $userId = null,
        ?int $companyId = null
    ): TransactionHead {
        return DB::transaction(function () use ($data, $userId, $companyId): TransactionHead {
            $companyId = $this->resolveCompanyId($companyId);

            if ($companyId > 0) {
                Company::query()->whereKey($companyId)->lockForUpdate()->firstOrFail();
            }

            $category = TransactionHead::normaliseCategory($data['category'] ?? null);

            $head = TransactionHead::query()->create([
                'company_id' => $companyId ?: null,
                'head_code' => $this->nextHeadCode($category, $companyId),
                'name' => $data['name'],
                'category' => $category,
                'default_primary_ledger_id' => $data['default_primary_ledger_id'],
                'help_text' => $data['help_text'] ?? null,
                'status' => $data['status'] ?? 'Active',

                // Compatibility columns are system-derived only. Accounting
                // Rules remain the source of party, money and Dr/Cr behavior.
                'nature' => TransactionHead::natureFromCategory($category),
                'default_party_type_id' => null,
                'default_movement' => $this->legacyMovement($category),
                'payment_method_required' => false,
                'party_required_mode' => 'No',
                'transaction_screen' => $this->screenFromCategory($category),
                'is_system_default' => false,
                'is_user_selectable' => true,
                'sort_order' => $this->nextSortOrder($companyId),
                'requires_party' => false,
                'requires_reference' => false,
                'created_by' => $userId,
                'updated_by' => $userId,
            ]);

            return $head->fresh([
                'defaultPrimaryLedger',
                'accountingRules.lines',
                'accountingRules.settlementType',
            ]);
        });
    }

    public function update(
        TransactionHead $head,
        array $data,
        ?int $userId = null
    ): TransactionHead {
        return DB::transaction(function () use ($head, $data, $userId): TransactionHead {
            $head->refresh();
            $category = TransactionHead::normaliseCategory($data['category'] ?? $head->category);
            $postingLedgerId = (int) $data['default_primary_ledger_id'];

            if ($head->vouchers()->exists()) {
                $categoryChanged = $category !== TransactionHead::normaliseCategory($head->category);
                $ledgerChanged = $postingLedgerId !== (int) $head->default_primary_ledger_id;

                if ($categoryChanged || $ledgerChanged) {
                    throw ValidationException::withMessages([
                        'transaction_head' => 'Category and Posting COA cannot be changed after this Transaction Head has transaction history. Deactivate it and create a new Transaction Head instead.',
                    ]);
                }
            }

            $head->update([
                // Business ID and company ownership are immutable.
                'name' => $data['name'],
                'category' => $category,
                'default_primary_ledger_id' => $postingLedgerId,
                'help_text' => $data['help_text'] ?? null,
                'status' => $data['status'] ?? $head->status,

                // Keep legacy columns synchronized but never user-editable.
                'nature' => TransactionHead::natureFromCategory($category),
                'default_party_type_id' => null,
                'default_movement' => $this->legacyMovement($category),
                'payment_method_required' => false,
                'party_required_mode' => 'No',
                'transaction_screen' => $this->screenFromCategory($category),
                'requires_party' => false,
                'requires_reference' => false,
                'updated_by' => $userId,
            ]);

            return $head->fresh([
                'defaultPrimaryLedger',
                'accountingRules.lines',
                'accountingRules.settlementType',
            ]);
        });
    }

    private function nextHeadCode(string $category, int $companyId): string
    {
        $prefix = 'TH-' . $this->categoryPrefix($category);

        $numbers = TransactionHead::query()
            ->withTrashed()
            ->when(
                $companyId > 0,
                fn ($query) => $query->where('company_id', $companyId),
                fn ($query) => $query->whereNull('company_id')
            )
            ->where('head_code', 'like', $prefix . '-%')
            ->lockForUpdate()
            ->pluck('head_code')
            ->map(function (?string $code) use ($prefix): int {
                if (! $code || ! preg_match('/^' . preg_quote($prefix, '/') . '-(\d+)$/', $code, $matches)) {
                    return 0;
                }

                return (int) $matches[1];
            });

        $next = ((int) $numbers->max()) + 1;

        return $prefix . '-' . str_pad((string) $next, 4, '0', STR_PAD_LEFT);
    }

    private function nextSortOrder(int $companyId): int
    {
        $maximum = TransactionHead::query()
            ->when(
                $companyId > 0,
                fn ($query) => $query->where('company_id', $companyId),
                fn ($query) => $query->whereNull('company_id')
            )
            ->max('sort_order');

        return ((int) $maximum) + 10;
    }

    private function categoryPrefix(string $category): string
    {
        return match ($category) {
            'Sales' => 'SAL',
            'Purchase' => 'PUR',
            'Receipt' => 'REC',
            'Payment' => 'PAY',
            'Banking' => 'BNK',
            'Expense' => 'EXP',
            'Income' => 'INC',
            'Owner / Equity' => 'EQT',
            'Asset' => 'AST',
            'Loan' => 'LON',
            'Employee' => 'EMP',
            'Opening' => 'OPN',
            'Adjustment' => 'ADJ',
            default => 'GEN',
        };
    }

    private function screenFromCategory(string $category): string
    {
        return match ($category) {
            'Opening' => 'Opening Balance Entry',
            'Owner / Equity' => 'Owner / Equity Entry',
            default => $category . ' Entry',
        };
    }

    private function legacyMovement(string $category): string
    {
        return match ($category) {
            'Banking', 'Adjustment' => 'No Movement',
            'Payment', 'Receipt', 'Employee' => 'Decrease',
            default => 'Increase',
        };
    }

    private function resolveCompanyId(?int $companyId): int
    {
        return (int) ($companyId ?: Company::query()->orderBy('id')->value('id') ?: 0);
    }
}
