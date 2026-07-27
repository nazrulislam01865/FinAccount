<x-layouts::accounting title="Journal Entries">
    <div class="hg-page-header">
        <div>
            <h1>Journal Entries</h1>
        </div>
    </div>

    @if ($journalEntries->isEmpty())
        <div class="hg-empty">No records found.</div>
    @else
        <div class="hg-table-wrap">
            <table class="hg-table">
                <thead>
                <tr>
                    <th>Voucher</th>
                    <th>Type</th>
                    <th>Account</th>
                    <th class="right">Debit</th>
                    <th class="right">Credit</th>
                </tr>
                </thead>
                <tbody>
                @foreach ($journalEntries as $journalEntry)
                    @php
                        $transaction = $journalEntry->transaction;
                    @endphp
                    @foreach ($journalEntry->lines as $line)
                        <tr>
                            <td><strong>{{ $journalEntry->voucher_no }}</strong><br><span class="hg-muted">{{ $journalEntry->entry_date?->format('Y-m-d') }}</span></td>
                            <td><span class="hg-badge {{ strtolower($transaction?->category ?? '') }}">{{ $categoryLabels[$transaction?->category] ?? $transaction?->category }}</span></td>
                            <td>
                                {{ $line->chartOfAccount?->code }} — {{ $line->chartOfAccount?->name }}
                                @if($line->moneyAccount)
                                    <br><small class="hg-muted">Money Account: {{ $line->moneyAccount->name }}</small>
                                @endif
                            </td>
                            <td class="right">{{ \App\Support\CompanyContext::money((float) $line->debit) }}</td>
                            <td class="right">{{ \App\Support\CompanyContext::money((float) $line->credit) }}</td>
                        </tr>
                    @endforeach
                @endforeach
                </tbody>
            </table>
        </div>

        <x-accounting.pagination :paginator="$journalEntries" item-label="journal entries" />
    @endif
</x-layouts::accounting>
