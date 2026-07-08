@php
    $settlementType = $settlement['settlement_type'] ?? \App\Support\TransactionTypes::CASH;
    $total = (float) $amount;
    $paid = (float) ($settlement['paid_amount'] ?? 0);
    $due = (float) ($settlement['due_amount'] ?? 0);
    $totalDebit = collect($lines ?? [])->sum(fn ($line) => (float) ($line['debit'] ?? 0));
    $totalCredit = collect($lines ?? [])->sum(fn ($line) => (float) ($line['credit'] ?? 0));
    $isBalanced = abs($totalDebit - $totalCredit) < 0.01;
@endphp

<div class="hg-notice">
    <strong>{{ $transactionTypeLabel }} — {{ $head->name }}</strong><br>
    Payment: {{ $settlementLabels[$settlementType] ?? $settlementType }}<br>
    Total: {{ \App\Support\CompanyContext::money($total) }}
    @if($settlementType === \App\Support\TransactionTypes::PARTIAL)
        | Paid/received now: {{ \App\Support\CompanyContext::money($paid) }}
        | Remaining due: {{ \App\Support\CompanyContext::money($due) }}
    @elseif($settlementType === \App\Support\TransactionTypes::CREDIT)
        | Remaining due: {{ \App\Support\CompanyContext::money($due) }}
    @endif
</div>

@if ($previewError)
    <div class="hg-notice" style="margin-top:12px">{{ $previewError }}</div>
@elseif (empty($lines))
    <div class="hg-muted" style="margin-top:12px">Complete the required fields to see the final summary.</div>
@else
    <div class="hg-preview-details" style="margin-top:14px">
        <div style="display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap;margin-bottom:10px">
            <div>
                <strong style="display:block;font-size:15px;color:#101828">Accounting Details</strong>
                <small class="hg-muted">The journal entry below will be posted automatically.</small>
            </div>
            <span style="display:inline-flex;align-items:center;padding:6px 10px;border-radius:999px;font-size:12px;font-weight:700;line-height:1;white-space:nowrap;color:{{ $isBalanced ? '#027a48' : '#b42318' }};background:{{ $isBalanced ? '#ecfdf3' : '#fef3f2' }};border:1px solid {{ $isBalanced ? '#abefc6' : '#fecdca' }}">
                {{ $isBalanced ? 'Balanced' : 'Not balanced' }}
            </span>
        </div>

        <div class="hg-table-wrap">
            <table class="hg-table">
                <thead>
                    <tr>
                        <th>Account</th>
                        <th class="right">Debit</th>
                        <th class="right">Credit</th>
                    </tr>
                </thead>
                <tbody>
                @foreach ($lines as $line)
                    @php
                        $lineDebit = (float) ($line['debit'] ?? 0);
                        $lineCredit = (float) ($line['credit'] ?? 0);
                    @endphp
                    <tr>
                        <td>
                            <strong>{{ $line['account']->code }}</strong>
                            <span class="hg-muted">— {{ $line['account']->name }}</span>
                        </td>
                        <td class="right">{{ $lineDebit > 0 ? \App\Support\CompanyContext::money($lineDebit) : '—' }}</td>
                        <td class="right">{{ $lineCredit > 0 ? \App\Support\CompanyContext::money($lineCredit) : '—' }}</td>
                    </tr>
                @endforeach
                <tr>
                    <td><strong>Total</strong></td>
                    <td class="right"><strong>{{ \App\Support\CompanyContext::money($totalDebit) }}</strong></td>
                    <td class="right"><strong>{{ \App\Support\CompanyContext::money($totalCredit) }}</strong></td>
                </tr>
                </tbody>
            </table>
        </div>
    </div>
@endif
