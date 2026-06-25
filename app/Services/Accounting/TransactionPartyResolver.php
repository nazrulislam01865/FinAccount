<?php

namespace App\Services\Accounting;

use App\Models\AccountingRule;
use App\Models\Party;
use App\Models\TransactionHead;
use App\Support\TransactionTypes;
use Illuminate\Validation\ValidationException;

class TransactionPartyResolver
{
    public function expectedPartyType(TransactionHead $head, AccountingRule $rule): string
    {
        return $head->party_type && $head->party_type !== 'Any'
            ? (string) $head->party_type
            : ((string) ($rule->party_type ?: 'Any'));
    }

    /**
     * Resolve the party required by a due/partial transaction.
     *
     * A transaction head identifies the required party type, not an arbitrary
     * specific party. When exactly one active matching party exists, it can be
     * selected safely and automatically. When more than one exists, the user
     * must choose so the receivable/payable ledger remains attached to the
     * correct customer, supplier, worker, owner, or lender.
     */
    public function resolveRequired(
        int $companyId,
        TransactionHead $head,
        AccountingRule $rule,
        mixed $submittedPartyId = null,
    ): Party {
        $expectedType = $this->expectedPartyType($head, $rule);
        $query = Party::query()
            ->with(['receivableAccount', 'payableAccount'])
            ->where('company_id', $companyId)
            ->where('is_active', true)
            ->when(
                $expectedType !== 'Any',
                fn ($query) => $query->where('type', $expectedType),
            );

        if (filled($submittedPartyId)) {
            $party = (clone $query)->find($submittedPartyId);

            if (! $party) {
                throw ValidationException::withMessages([
                    'party_id' => $expectedType === 'Any'
                        ? 'Select a valid party for this transaction.'
                        : 'Select a valid '.$expectedType.' for this transaction.',
                ]);
            }

            return $party;
        }

        $matchingParties = $query->orderBy('name')->limit(2)->get();

        if ($matchingParties->count() === 1) {
            return $matchingParties->first();
        }

        if ($matchingParties->isEmpty()) {
            throw ValidationException::withMessages([
                'party_id' => $expectedType === 'Any'
                    ? 'Create an active party before recording this due transaction.'
                    : 'Create an active '.$expectedType.' party before recording this transaction.',
            ]);
        }

        throw ValidationException::withMessages([
            'party_id' => TransactionTypes::partyLabel((string) $head->category)
                .' must be selected because more than one matching party is available.',
        ]);
    }
}
