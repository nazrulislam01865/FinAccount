@php
    $settlementType = $settlement['settlement_type'] ?? \App\Support\TransactionTypes::CASH;
    $total = (float) $amount;
    $paid = (float) ($settlement['paid_amount'] ?? 0);
    $due = (float) ($settlement['due_amount'] ?? 0);
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
    <details style="margin-top:14px">
        <summary><strong>Accounting details</strong></summary>
        <div class="hg-table-wrap" style="margin-top:10px">
            <table class="hg-table">
                <thead><tr><th>Account</th><th class="right">Debit</th><th class="right">Credit</th></tr></thead>
                <tbody>
                @foreach ($lines as $line)
                    <tr>
                        <td>{{ $line['account']->code }} — {{ $line['account']->name }}</td>
                        <td class="right">{{ \App\Support\CompanyContext::money((float) $line['debit']) }}</td>
                        <td class="right">{{ \App\Support\CompanyContext::money((float) $line['credit']) }}</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    </details>
@endif
