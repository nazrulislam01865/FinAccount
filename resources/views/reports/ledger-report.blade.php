<x-layouts::accounting title="Ledger Report">
    <div class="hg-report-page">
        <div class="hg-page-header hg-report-page-header">
            <div>
                <h1>Ledger Report</h1>
                <p>Shows account-wise posted journal movement with opening and running balance.</p>
            </div>
        </div>

        <x-reports.partials.filter-toolbar
            :action="route('reports.ledger-report')"
            :export-url="route('reports.ledger-report', request()->query() + ['export' => 'csv'])"
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
                <span>Account</span>
                <select name="account_id" required>
                    @foreach($report['accounts'] as $account)
                        <option value="{{ $account->id }}" @selected((int) $report['account_id'] === (int) $account->id)>
                            {{ $account->code }} — {{ $account->name }}
                        </option>
                    @endforeach
                </select>
            </label>
            <label>
                <span>Party</span>
                <select name="party_id">
                    <option value="">All parties</option>
                    @foreach($report['parties'] as $party)
                        <option value="{{ $party->id }}" @selected((int) ($report['party_id'] ?? 0) === (int) $party->id)>
                            {{ $party->code }} — {{ $party->name }}
                        </option>
                    @endforeach
                </select>
            </label>
            <label>
                <span>Search</span>
                <input type="search" name="search" value="{{ request('search') }}" placeholder="Voucher or description">
            </label>
        </x-reports.partials.filter-toolbar>

        <div class="hg-grid hg-grid-4 hg-report-summary">
            <section class="hg-card hg-metric"><span class="label">Opening Debit</span><div class="value">{{ \App\Support\CompanyContext::money($report['opening_debit']) }}</div></section>
            <section class="hg-card hg-metric"><span class="label">Opening Credit</span><div class="value">{{ \App\Support\CompanyContext::money($report['opening_credit']) }}</div></section>
            <section class="hg-card hg-metric"><span class="label">Period Debit</span><div class="value">{{ \App\Support\CompanyContext::money($report['period_debit']) }}</div></section>
            <section class="hg-card hg-metric"><span class="label">Period Credit</span><div class="value">{{ \App\Support\CompanyContext::money($report['period_credit']) }}</div></section>
        </div>

        <section class="hg-card">
            <h2 class="hg-card-title">
                Ledger Details
                @if($report['account'])
                    <span class="hg-muted">— {{ $report['account']->code }} {{ $report['account']->name }}</span>
                @endif
            </h2>

            @if(! $report['account'])
                <div class="hg-empty">No account found.</div>
            @else
                <div class="hg-table-wrap">
                    <table class="hg-table hg-report-table">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Voucher</th>
                                <th>Description</th>
                                <th>Party</th>
                                <th class="right">Debit</th>
                                <th class="right">Credit</th>
                                <th class="right">Balance</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr class="hg-ledger-opening-row">
                                <td>{{ $report['from_date'] }}</td>
                                <td>Opening</td>
                                <td>Opening balance before selected period</td>
                                <td>{{ $report['party'] ? $report['party']->code.' — '.$report['party']->name : 'All' }}</td>
                                <td class="right">{{ \App\Support\CompanyContext::money($report['opening_debit']) }}</td>
                                <td class="right">{{ \App\Support\CompanyContext::money($report['opening_credit']) }}</td>
                                <td class="right">{{ \App\Support\CompanyContext::money(max($report['opening_debit'], $report['opening_credit'])) }} {{ $report['opening_debit'] >= $report['opening_credit'] ? 'Dr' : 'Cr' }}</td>
                            </tr>
                            @forelse($report['rows'] as $row)
                                <tr>
                                    <td>{{ $row['date'] }}</td>
                                    <td><strong>{{ $row['voucher_no'] }}</strong><br><span class="hg-muted">{{ $row['transaction_head'] }}</span></td>
                                    <td>{{ $row['description'] }}</td>
                                    <td>{{ $row['party'] ?? '—' }}</td>
                                    <td class="right">{{ \App\Support\CompanyContext::money($row['debit']) }}</td>
                                    <td class="right">{{ \App\Support\CompanyContext::money($row['credit']) }}</td>
                                    <td class="right"><strong>{{ \App\Support\CompanyContext::money($row['balance']) }} {{ $row['balance_type'] }}</strong></td>
                                </tr>
                            @empty
                                <tr><td colspan="7"><div class="hg-empty">No posted ledger movement found for this period.</div></td></tr>
                            @endforelse
                        </tbody>
                        <tfoot>
                            <tr>
                                <th colspan="4">Period Total</th>
                                <th class="right">{{ \App\Support\CompanyContext::money($report['period_debit']) }}</th>
                                <th class="right">{{ \App\Support\CompanyContext::money($report['period_credit']) }}</th>
                                <th></th>
                            </tr>
                            <tr>
                                <th colspan="4">Closing Balance</th>
                                <th class="right">{{ \App\Support\CompanyContext::money($report['closing_debit']) }}</th>
                                <th class="right">{{ \App\Support\CompanyContext::money($report['closing_credit']) }}</th>
                                <th></th>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            @endif
        </section>
    </div>
</x-layouts::accounting>
