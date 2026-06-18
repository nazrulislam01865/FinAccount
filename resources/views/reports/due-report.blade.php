<x-layouts::accounting title="Due Report">
    <div class="hg-report-page">
        <div class="hg-page-header hg-report-page-header">
            <div>
                <h1>Due Report</h1>
                <p>Shows customer receivables and supplier or lender payables from active party mappings and posted journal lines.</p>
            </div>
        </div>

        <x-reports.partials.filter-toolbar
            :action="route('reports.due-report')"
            :export-url="route('reports.due-report', request()->query() + ['export' => 'csv'])"
        >
            <label>
                <span>As of date</span>
                <input type="date" name="as_of_date" value="{{ $report['as_of_date'] }}">
            </label>
            <label>
                <span>Due type</span>
                <select name="due_type">
                    <option value="all" @selected($report['due_type'] === 'all')>All dues</option>
                    <option value="receivable" @selected($report['due_type'] === 'receivable')>Receivable only</option>
                    <option value="payable" @selected($report['due_type'] === 'payable')>Payable only</option>
                </select>
            </label>
            <label>
                <span>Search party</span>
                <input type="search" name="search" value="{{ request('search') }}" placeholder="Party code, name or type">
            </label>
            <label class="hg-report-check">
                <input type="checkbox" name="include_zero_balances" value="1" @checked($report['include_zero_balances'])>
                <span>Show zero balances</span>
            </label>
        </x-reports.partials.filter-toolbar>

        <div class="hg-grid hg-grid-4 hg-report-summary">
            <section class="hg-card hg-metric"><span class="label">Total Receivable</span><div class="value">{{ \App\Support\CompanyContext::money($report['total_receivable']) }}</div></section>
            <section class="hg-card hg-metric"><span class="label">Total Payable</span><div class="value">{{ \App\Support\CompanyContext::money($report['total_payable']) }}</div></section>
            <section class="hg-card hg-metric"><span class="label">Net Due Position</span><div class="value">{{ \App\Support\CompanyContext::money($report['total_receivable'] - $report['total_payable']) }}</div></section>
            <section class="hg-card hg-metric"><span class="label">Over 90 Days</span><div class="value">{{ \App\Support\CompanyContext::money($report['aging_totals']['days_90_plus']) }}</div></section>
        </div>

        <section class="hg-card">
            <h2 class="hg-card-title">Due Details</h2>
            @if($report['rows']->isEmpty())
                <div class="hg-empty">No records found.</div>
            @else
                <div class="hg-table-wrap">
                    <table class="hg-table hg-report-table">
                        <thead>
                            <tr>
                                <th>Type</th>
                                <th>Party</th>
                                <th>Account</th>
                                <th class="right">Opening</th>
                                <th class="right">Debit</th>
                                <th class="right">Credit</th>
                                <th class="right">Closing Due</th>
                                <th class="right">0-30</th>
                                <th class="right">31-60</th>
                                <th class="right">61-90</th>
                                <th class="right">90+</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($report['rows'] as $row)
                                <tr>
                                    <td><span class="hg-badge {{ strtolower($row['due_type']) }}">{{ $row['due_type'] }}</span></td>
                                    <td><strong>{{ $row['party_code'] }}</strong> — {{ $row['party_name'] }}<br><span class="hg-muted">{{ $row['party_type'] }}</span></td>
                                    <td>{{ $row['account_code'] }} — {{ $row['account_name'] }}</td>
                                    <td class="right">{{ \App\Support\CompanyContext::money($row['opening_balance']) }}</td>
                                    <td class="right">{{ \App\Support\CompanyContext::money($row['period_debit']) }}</td>
                                    <td class="right">{{ \App\Support\CompanyContext::money($row['period_credit']) }}</td>
                                    <td class="right"><strong>{{ \App\Support\CompanyContext::money($row['closing_balance']) }}</strong></td>
                                    <td class="right">{{ \App\Support\CompanyContext::money($row['current']) }}</td>
                                    <td class="right">{{ \App\Support\CompanyContext::money($row['days_31_60']) }}</td>
                                    <td class="right">{{ \App\Support\CompanyContext::money($row['days_61_90']) }}</td>
                                    <td class="right">{{ \App\Support\CompanyContext::money($row['days_90_plus']) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                        <tfoot>
                            <tr>
                                <th colspan="6" class="right">Total</th>
                                <th class="right">{{ \App\Support\CompanyContext::money($report['total_receivable'] + $report['total_payable']) }}</th>
                                <th class="right">{{ \App\Support\CompanyContext::money($report['aging_totals']['current']) }}</th>
                                <th class="right">{{ \App\Support\CompanyContext::money($report['aging_totals']['days_31_60']) }}</th>
                                <th class="right">{{ \App\Support\CompanyContext::money($report['aging_totals']['days_61_90']) }}</th>
                                <th class="right">{{ \App\Support\CompanyContext::money($report['aging_totals']['days_90_plus']) }}</th>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            @endif
        </section>
    </div>
</x-layouts::accounting>
