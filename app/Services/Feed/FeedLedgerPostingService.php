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
        $head = $this->head($settings, (int) $user->company_id, TransactionTypes::PURCHASE);

        return $this->transactionPostingService->postForHead([
            ...$data,
            'category' => TransactionTypes::PURCHASE,
            'transaction_head_id' => $head->id,
        ], $user, $head);
    }

    /** @param array<string, mixed> $data */
    public function postSale(array $data, User $user, FeedSetting $settings): Transaction
    {
        $head = $this->head($settings, (int) $user->company_id, TransactionTypes::SALE);

        return $this->transactionPostingService->postForHead([
            ...$data,
            'category' => TransactionTypes::SALE,
            'transaction_head_id' => $head->id,
        ], $user, $head);
    }

    private function head(FeedSetting $settings, int $companyId, string $category): TransactionHead
    {
        $head = $category === TransactionTypes::SALE
            ? $settings->saleTransactionHead
            : $settings->purchaseTransactionHead;

        if (
            ! $head
            || (int) $head->company_id !== $companyId
            || ! $head->is_active
            || strcasecmp((string) $head->category, $category) !== 0
        ) {
            throw ValidationException::withMessages([
                'feed_setup' => 'The Feed '.($category === TransactionTypes::SALE ? 'Sale' : 'Purchase').' transaction head is unavailable or inactive. Repair it from Transaction Heads.',
            ]);
        }

        return $head;
    }
}
