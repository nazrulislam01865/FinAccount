<?php

namespace App\Services\Accounting;

use App\Models\Transaction;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class TransactionDeletionService
{
    public function delete(Transaction $transaction, User $user): void
    {
        if ($transaction->company_id !== $user->company_id) {
            abort(404);
        }

        DB::transaction(function () use ($transaction, $user): void {
            $lockedTransaction = Transaction::query()
                ->where('company_id', $user->company_id)
                ->lockForUpdate()
                ->findOrFail($transaction->id);

            $journalEntry = $lockedTransaction->journalEntry()->first();

            if ($journalEntry) {
                $journalEntry->delete();
            }

            $lockedTransaction->delete();
        }, attempts: 5);
    }
}
