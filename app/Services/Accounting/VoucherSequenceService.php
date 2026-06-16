<?php

namespace App\Services\Accounting;

use App\Models\AccountingOption;
use App\Models\DocumentSequence;
use App\Models\Transaction;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class VoucherSequenceService
{
    public function __construct(private readonly AccountingOptionService $optionService) {}

    /**
     * @return array{
     *     sequences:Collection<int,DocumentSequence>,
     *     categoryLabels:array<string,string>,
     *     availableCategories:Collection<int,AccountingOption>,
     *     transactionCategories:Collection<int,AccountingOption>
     * }
     */
    public function pageData(int $companyId): array
    {
        $allCategories = $this->optionService->forGroup(
            AccountingOption::GROUP_TRANSACTION_CATEGORY,
            activeOnly: false,
        );
        $transactionCategories = $allCategories->where('is_active', true)->values();
        $sortOrder = $allCategories->pluck('sort_order', 'value');

        $sequences = DocumentSequence::query()
            ->where('company_id', $companyId)
            ->get()
            ->sortBy(fn (DocumentSequence $sequence): int => $sequence->category === null
                ? 99999
                : (int) ($sortOrder[$sequence->category] ?? 9999))
            ->values();

        $configuredCategories = $sequences
            ->whereNotNull('category')
            ->pluck('category');

        $availableCategories = $transactionCategories
            ->reject(fn (AccountingOption $category): bool => $configuredCategories->contains($category->value))
            ->values();

        return [
            'sequences' => $sequences,
            'categoryLabels' => $allCategories->pluck('label', 'value')->all(),
            'availableCategories' => $availableCategories,
            'transactionCategories' => $transactionCategories,
        ];
    }

    /** @param array<string, mixed> $data */
    public function create(array $data, int $companyId): DocumentSequence
    {
        $category = trim((string) $data['category']);
        $prefix = strtoupper(trim((string) $data['prefix']));
        $nextNumber = (int) $data['next_number'];
        $padding = (int) $data['padding'];

        $this->ensureActiveCategory($category);
        $this->ensureCategoryAvailable($companyId, $category);
        $this->validatePrefixAndPreview($companyId, $prefix, $nextNumber, $padding);

        return DB::transaction(fn (): DocumentSequence => DocumentSequence::query()->create([
            'company_id' => $companyId,
            'category' => $category,
            'prefix' => $prefix,
            'next_number' => $nextNumber,
            'padding' => $padding,
            'is_active' => (bool) ($data['is_active'] ?? true),
        ]), attempts: 5);
    }

    /** @param array<string, mixed> $data */
    public function update(DocumentSequence $sequence, array $data, int $companyId): DocumentSequence
    {
        abort_unless($sequence->company_id === $companyId, 404);

        $category = trim((string) $data['category']);
        $prefix = strtoupper(trim((string) $data['prefix']));
        $nextNumber = (int) $data['next_number'];
        $padding = (int) $data['padding'];

        $this->ensureActiveCategory($category);
        $this->ensureCategoryAvailable($companyId, $category, $sequence->id);

        if ($nextNumber < $sequence->next_number) {
            throw ValidationException::withMessages([
                'next_number' => 'The next number cannot be lower than the current sequence number.',
            ]);
        }

        $this->validatePrefixAndPreview(
            $companyId,
            $prefix,
            $nextNumber,
            $padding,
            $sequence->id,
        );

        DB::transaction(fn () => $sequence->update([
            'category' => $category,
            'prefix' => $prefix,
            'next_number' => $nextNumber,
            'padding' => $padding,
            'is_active' => (bool) $data['is_active'],
        ]), attempts: 5);

        return $sequence->refresh();
    }

    private function ensureActiveCategory(string $category): void
    {
        $exists = AccountingOption::query()
            ->forGroup(AccountingOption::GROUP_TRANSACTION_CATEGORY)
            ->active()
            ->where('value', $category)
            ->exists();

        if (! $exists) {
            throw ValidationException::withMessages([
                'category' => 'Select an active transaction category.',
            ]);
        }
    }

    private function ensureCategoryAvailable(int $companyId, string $category, ?int $ignoreId = null): void
    {
        $query = DocumentSequence::query()
            ->where('company_id', $companyId)
            ->where('category', $category);

        if ($ignoreId !== null) {
            $query->where('id', '!=', $ignoreId);
        }

        if ($query->exists()) {
            throw ValidationException::withMessages([
                'category' => 'Voucher numbering is already configured for this transaction category.',
            ]);
        }
    }

    private function validatePrefixAndPreview(
        int $companyId,
        string $prefix,
        int $nextNumber,
        int $padding,
        ?int $ignoreSequenceId = null,
    ): void {
        $duplicatePrefixQuery = DocumentSequence::query()
            ->where('company_id', $companyId)
            ->where('prefix', $prefix);

        if ($ignoreSequenceId !== null) {
            $duplicatePrefixQuery->where('id', '!=', $ignoreSequenceId);
        }

        if ($duplicatePrefixQuery->exists()) {
            throw ValidationException::withMessages([
                'prefix' => 'This prefix is already used by another transaction category.',
            ]);
        }

        $preview = $prefix.'-'.str_pad((string) $nextNumber, $padding, '0', STR_PAD_LEFT);

        if (Transaction::query()
            ->where('company_id', $companyId)
            ->where('voucher_no', $preview)
            ->exists()) {
            throw ValidationException::withMessages([
                'next_number' => 'The resulting voucher number already exists. Increase the next number.',
            ]);
        }
    }
}
