<?php

namespace App\Services\Accounting;

use App\Models\Transaction;
use App\Models\User;
use App\Services\Feed\FeedPostingService;
use Illuminate\Support\Facades\DB;

class TransactionDeletionService
{
    public function __construct(
        private readonly FeedPostingService $feedPostingService,
    ) {}

    public function delete(Transaction $transaction, User $user): void
    {
        if ($transaction->company_id !== $user->company_id) {
            abort(404);
        }

        DB::transaction(function () use ($transaction, $user): void {
            $lockedTransaction = Transaction::query()
                ->with('feedDocument.movements')
                ->where('company_id', $user->company_id)
                ->lockForUpdate()
                ->findOrFail($transaction->id);

            if ($lockedTransaction->feedDocument) {
                $this->feedPostingService->reverseDocument($lockedTransaction->feedDocument);
            }

            $journalEntry = $lockedTransaction->journalEntry()->first();
            $journalEntryId = $journalEntry?->id;

            $lockedTransaction->salesInvoice()->delete();
            $lockedTransaction->attachments()->get()->each->delete();
            $lockedTransaction->payments()->delete();

            if ($journalEntry) {
                $journalEntry->delete();
            }

            $transactionId = $lockedTransaction->id;
            $lockedTransaction->delete();

            if (Transaction::query()->whereKey($transactionId)->exists()) {
                throw new \RuntimeException('Transaction database deletion verification failed.');
            }

            if ($journalEntryId !== null && \App\Models\JournalEntry::query()->whereKey($journalEntryId)->exists()) {
                throw new \RuntimeException('Journal database deletion verification failed.');
            }
        }, attempts: 5);
    }
}
