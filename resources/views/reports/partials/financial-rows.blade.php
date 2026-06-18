@if($rows->isEmpty())
    <div class="hg-empty">No records found.</div>
@else
    <div class="hg-table-wrap">
        <table class="hg-table hg-report-table">
            <thead>
                <tr><th>Account</th><th class="right">Amount</th></tr>
            </thead>
            <tbody>
                @foreach($rows as $row)
                    <tr>
                        <td>
                            <strong>{{ $row['code'] }}</strong> — {{ $row['name'] }}
                            @if(! $row['is_active'])<br><span class="hg-muted">Inactive</span>@endif
                        </td>
                        <td class="right">{{ \App\Support\CompanyContext::money($row[$amountKey] ?? 0) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@endif
