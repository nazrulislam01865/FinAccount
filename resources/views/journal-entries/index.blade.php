<x-layouts::accounting title="Journal Entries">
    <div class="hg-page-header">
        <div>
            <h1>Journal Entries</h1>
        </div>
    </div>

    @if ($journalLines->isEmpty())
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
                @foreach ($journalLines as $line)
                    @php
                        $transaction = $line->journalEntry?->transaction;
                    @endphp
                    <tr>
                        <td><strong>{{ $line->journalEntry?->voucher_no }}</strong><br><span class="hg-muted">{{ $line->journalEntry?->entry_date?->format('Y-m-d') }}</span></td>
                        <td><span class="hg-badge {{ strtolower($transaction?->category ?? '') }}">{{ $categoryLabels[$transaction?->category] ?? $transaction?->category }}</span></td>
                        <td>{{ $line->chartOfAccount?->code }} — {{ $line->chartOfAccount?->name }}</td>
                        <td class="right">{{ \App\Support\CompanyContext::money((float) $line->debit) }}</td>
                        <td class="right">{{ \App\Support\CompanyContext::money((float) $line->credit) }}</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    @endif
</x-layouts::accounting>
