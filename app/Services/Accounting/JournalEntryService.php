<?php

namespace App\Services\Accounting;

use App\Models\JournalEntry;
use App\Models\JournalLine;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class JournalEntryService
{

    /** @return LengthAwarePaginator<JournalEntry> */
    public function entriesForCompany(int $companyId, int $perPage = 20): LengthAwarePaginator
    {
        return JournalEntry::query()
            ->with([
                'transaction',
                'lines' => fn ($query) => $query
                    ->with(['chartOfAccount', 'moneyAccount'])
                    ->orderBy('sequence'),
            ])
            ->where('company_id', $companyId)
            ->where('status', 'posted')
            ->orderBy('id')
            ->paginate($perPage)
            ->withQueryString();
    }
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
