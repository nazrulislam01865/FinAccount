<x-layouts::accounting title="Trial Balance">
    <div class="hg-report-page">
        <div class="hg-page-header hg-report-page-header">
            <div>
                <h1>Trial Balance</h1>
                <p>Checks debit and credit balances from opening values and posted journal lines for the selected period.</p>
            </div>
        </div>

        <x-reports.partials.filter-toolbar
            :action="route('reports.trial-balance')"
            :export-url="route('reports.trial-balance', request()->query() + ['export' => 'csv'])"
        >
            <label>
                <span>From date</span>
                <input type="date" name="from_date" value="{{ $report['from_date'] }}">
            </label>
            <label>
                <span>To date</span>
                <input type="date" name="to_date" value="{{ $report['to_date'] }}">
            </label>
            <label>
                <span>Account type</span>
                <select name="account_type">
                    <option value="all" @selected($report['account_type'] === 'all')>All types</option>
                    @foreach($report['account_types'] as $type)
                        <option value="{{ $type }}" @selected($report['account_type'] === $type)>{{ $type }}</option>
                    @endforeach
                </select>
            </label>
            <label>
                <span>Balance type</span>
                <select name="balance_type">
                    <option value="all" @selected($report['balance_type'] === 'all')>All balances</option>
                    <option value="debit" @selected($report['balance_type'] === 'debit')>Debit only</option>
                    <option value="credit" @selected($report['balance_type'] === 'credit')>Credit only</option>
                    <option value="zero" @selected($report['balance_type'] === 'zero')>Zero only</option>
                </select>
            </label>
            <label>
                <span>Search account</span>
                <input type="search" name="search" value="{{ request('search') }}" placeholder="Code or account name">
            </label>
            <label class="hg-report-check">
                <input type="checkbox" name="include_zero_balances" value="1" @checked($report['include_zero_balances'])>
                <span>Show zero balances</span>
            </label>
        </x-reports.partials.filter-toolbar>

        <div class="hg-grid hg-grid-3 hg-report-summary">
            <section class="hg-card hg-metric"><span class="label">Closing Debit</span><div class="value">{{ \App\Support\CompanyContext::money($report['total_closing_debit']) }}</div></section>
            <section class="hg-card hg-metric"><span class="label">Closing Credit</span><div class="value">{{ \App\Support\CompanyContext::money($report['total_closing_credit']) }}</div></section>
            <section class="hg-card hg-metric"><span class="label">Difference</span><div class="value">{{ \App\Support\CompanyContext::money($report['difference']) }}</div><small>{{ $report['is_balanced'] ? 'Balanced' : 'Needs review' }}</small></section>
        </div>

        <section class="hg-card">
            <h2 class="hg-card-title">Trial Balance Details</h2>
            @if($report['rows']->isEmpty())
                <div class="hg-empty">No records found.</div>
            @else
                <div class="hg-table-wrap">
                    <table class="hg-table hg-report-table hg-trial-balance-table">
                        <thead>
                            <tr>
                                <th>Account</th>
                                <th>Type</th>
                                <th class="right">Opening Dr</th>
                                <th class="right">Opening Cr</th>
                                <th class="right">Period Dr</th>
                                <th class="right">Period Cr</th>
                                <th class="right">Closing Dr</th>
                                <th class="right">Closing Cr</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($report['rows'] as $row)
                                <tr>
                                    <td>
                                        <strong>{{ $row['code'] }}</strong> — {{ $row['name'] }}
                                        @if(! $row['is_active'])<br><span class="hg-muted">Inactive</span>@endif
                                    </td>
                                    <td><span class="hg-badge {{ strtolower($row['type']) }}">{{ $row['type'] }}</span></td>
                                    <td class="right">{{ \App\Support\CompanyContext::money($row['opening_debit']) }}</td>
                                    <td class="right">{{ \App\Support\CompanyContext::money($row['opening_credit']) }}</td>
                                    <td class="right">{{ \App\Support\CompanyContext::money($row['period_debit']) }}</td>
                                    <td class="right">{{ \App\Support\CompanyContext::money($row['period_credit']) }}</td>
                                    <td class="right"><strong>{{ \App\Support\CompanyContext::money($row['closing_debit']) }}</strong></td>
                                    <td class="right"><strong>{{ \App\Support\CompanyContext::money($row['closing_credit']) }}</strong></td>
                                </tr>
                            @endforeach
                        </tbody>
                        <tfoot>
                            <tr>
                                <th colspan="2">Total</th>
                                <th class="right">{{ \App\Support\CompanyContext::money($report['total_opening_debit']) }}</th>
                                <th class="right">{{ \App\Support\CompanyContext::money($report['total_opening_credit']) }}</th>
                                <th class="right">{{ \App\Support\CompanyContext::money($report['total_period_debit']) }}</th>
                                <th class="right">{{ \App\Support\CompanyContext::money($report['total_period_credit']) }}</th>
                                <th class="right">{{ \App\Support\CompanyContext::money($report['total_closing_debit']) }}</th>
                                <th class="right">{{ \App\Support\CompanyContext::money($report['total_closing_credit']) }}</th>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            @endif
        </section>
    </div>
</x-layouts::accounting>
