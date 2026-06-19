@php
    $effectiveLines = app(\App\Services\Accounting\TransactionSettlementService::class)->effectiveLines($rule);
    $amountBasisLabels = [
        \App\Models\AccountingRuleLine::BASIS_TOTAL => 'Total Amount',
        \App\Models\AccountingRuleLine::BASIS_PAID => 'Paid Amount',
        \App\Models\AccountingRuleLine::BASIS_DUE => 'Due Amount',
    ];
@endphp

<div class="hg-notice">
    <b>Rule:</b> {{ $rule->name }}<br>
    <b>Rule-based posting lines:</b>
    @foreach($effectiveLines as $ruleLine)
        @php
            $side = $ruleLine instanceof \App\Models\AccountingRuleLine ? $ruleLine->line_side : ($ruleLine['line_side'] ?? '');
            $source = $ruleLine instanceof \App\Models\AccountingRuleLine ? $ruleLine->account_source : ($ruleLine['account_source'] ?? '');
            $basis = $ruleLine instanceof \App\Models\AccountingRuleLine ? $ruleLine->amount_basis : ($ruleLine['amount_basis'] ?? '');
        @endphp
        <br>{{ ucfirst($side) }}: {{ $sourceLabels[$source] ?? $source }} — {{ $amountBasisLabels[$basis] ?? $basis }}
    @endforeach
    <br>
    Required party: {{ $rule->party_required ? ($partyTypeLabels[$rule->party_type] ?? $rule->party_type) : 'No' }} |
    Money account: {{ $rule->money_required ? 'Yes' : 'No' }}<br>
    Head posting COA: {{ $head->postingAccount->name }}
    @if(($settlement['settlement_type'] ?? 'normal') === 'partial')
        <br>
        <b>Rule-based split:</b>
        Total {{ \App\Support\CompanyContext::money((float) $amount) }} |
        Paid {{ \App\Support\CompanyContext::money((float) ($settlement['paid_amount'] ?? 0)) }} |
        Due {{ \App\Support\CompanyContext::money((float) ($settlement['due_amount'] ?? 0)) }}
    @endif
</div>

<div class="hg-preview-space"></div>

<div class="hg-journal">
    @if ($previewError)
        <div class="hg-muted">{{ $previewError }}</div>
    @elseif (empty($lines))
        <div class="hg-muted">Select the required fields.</div>
    @else
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
                    <tr>
                        <td>{{ $line['account']->code }} — {{ $line['account']->name }}</td>
                        <td class="right">{{ \App\Support\CompanyContext::money((float) $line['debit']) }}</td>
                        <td class="right">{{ \App\Support\CompanyContext::money((float) $line['credit']) }}</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>

<div class="hg-preview-space"></div>
