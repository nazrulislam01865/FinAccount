<div class="hg-notice">
    <b>Rule:</b> {{ $rule->name }}<br>
    Debit: {{ $sourceLabels[$rule->debit_source] ?? $rule->debit_source }}. Credit: {{ $sourceLabels[$rule->credit_source] ?? $rule->credit_source }}.<br>
    Required party: {{ $rule->party_required ? ($partyTypeLabels[$rule->party_type] ?? $rule->party_type) : 'No' }} |
    Money account: {{ $rule->money_required ? 'Yes' : 'No' }}<br>
    Head posting COA: {{ $head->postingAccount->name }}
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
