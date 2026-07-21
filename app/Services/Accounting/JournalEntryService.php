<?php

namespace App\Services\Accounting;

use App\Models\JournalLine;
use Illuminate\Support\Collection;

class JournalEntryService
{
    /** @return Collection<int, JournalLine> */
    public function linesForCompany(int $companyId): Collection
    {
        return JournalLine::query()
            ->with([
                'chartOfAccount',
                'moneyAccount',
                'journalEntry.transaction',
            ])
            ->where('journal_lines.company_id', $companyId)
            ->whereHas('journalEntry', fn ($query) => $query->where('status', 'posted'))
            ->join('journal_entries', 'journal_entries.id', '=', 'journal_lines.journal_entry_id')
            ->select('journal_lines.*')
            ->orderBy('journal_entries.id')
            ->orderBy('journal_lines.sequence')
            ->get();
    }
}
