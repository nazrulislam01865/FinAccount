<?php

namespace App\Services\Feed;

use App\Models\Feed\FeedSetting;
use App\Models\Transaction;
use App\Models\TransactionHead;
use App\Models\User;
use App\Services\Accounting\TransactionPostingService;
use App\Support\TransactionTypes;
use Illuminate\Validation\ValidationException;

class FeedLedgerPostingService
{
    public function __construct(
        private readonly TransactionPostingService $transactionPostingService,
    ) {}

    /** @param array<string, mixed> $data */
    public function postPurchase(array $data, User $user, FeedSetting $settings): Transaction
    {
        $head = $this->head($settings, (int) $user->company_id, TransactionTypes::PURCHASE, $data['transaction_head_id'] ?? null);

        return $this->transactionPostingService->postForHead([
            ...$data,
            'category' => TransactionTypes::PURCHASE,
            'transaction_head_id' => $head->id,
        ], $user, $head);
    }

    /** @param array<string, mixed> $data */
    public function postSale(array $data, User $user, FeedSetting $settings): Transaction
    {
        $head = $this->head($settings, (int) $user->company_id, TransactionTypes::SALE, $data['transaction_head_id'] ?? null);

        return $this->transactionPostingService->postForHead([
            ...$data,
            'category' => TransactionTypes::SALE,
            'transaction_head_id' => $head->id,
        ], $user, $head);
    }

    private function head(FeedSetting $settings, int $companyId, string $category, mixed $selectedHeadId = null): TransactionHead
    {
        $head = filled($selectedHeadId)
            ? TransactionHead::query()
                ->with('postingAccount')
                ->where('company_id', $companyId)
                ->whereRaw('LOWER(category) = ?', [strtolower($category)])
                ->where('is_active', true)
                ->find((int) $selectedHeadId)
            : ($category === TransactionTypes::SALE
                ? $settings->saleTransactionHead
                : $settings->purchaseTransactionHead);

        if (
            ! $head
            || (int) $head->company_id !== $companyId
            || ! $head->is_active
            || strcasecmp((string) $head->category, $category) !== 0
            || ! $head->postingAccount
            || (int) $head->postingAccount->company_id !== $companyId
            || ! $head->postingAccount->is_active
        ) {
            throw ValidationException::withMessages([
                'transaction_head_id' => 'The selected Feed '.($category === TransactionTypes::SALE ? 'Sale' : 'Purchase').' transaction head is unavailable or inactive.',
            ]);
        }

        $requiredType = $category === TransactionTypes::SALE ? 'Income' : 'Asset';

        if ($head->postingAccount->type !== $requiredType || (int) $head->postingAccount->level !== 3) {
            throw ValidationException::withMessages([
                'transaction_head_id' => 'Feed '.($category === TransactionTypes::SALE ? 'Sale' : 'Purchase').' must use a transaction head linked with an active level-3 '.$requiredType.' account.',
            ]);
        }

        return $head;
    }
}
