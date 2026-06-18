<x-layouts::accounting title="Income Statement">
    <div class="hg-report-page">
        <div class="hg-page-header hg-report-page-header">
            <div>
                <h1>Income Statement</h1>
                <p>Shows income, expenses, and net profit or loss from posted journals within the selected period.</p>
            </div>
        </div>

        <x-reports.partials.filter-toolbar
            :action="route('reports.income-statement')"
            :export-url="route('reports.income-statement', request()->query() + ['export' => 'csv'])"
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
                <span>Search account</span>
                <input type="search" name="search" value="{{ request('search') }}" placeholder="Code or account name">
            </label>
            <label class="hg-report-check">
                <input type="checkbox" name="include_zero_balances" value="1" @checked($report['include_zero_balances'])>
                <span>Show zero balances</span>
            </label>
        </x-reports.partials.filter-toolbar>

        <div class="hg-grid hg-grid-3 hg-report-summary">
            <section class="hg-card hg-metric"><span class="label">Total Income</span><div class="value">{{ \App\Support\CompanyContext::money($report['income']) }}</div></section>
            <section class="hg-card hg-metric"><span class="label">Total Expense</span><div class="value">{{ \App\Support\CompanyContext::money($report['expense']) }}</div></section>
            <section class="hg-card hg-metric"><span class="label">Net Profit / Loss</span><div class="value">{{ \App\Support\CompanyContext::money($report['net_profit']) }}</div></section>
        </div>

        <div class="hg-grid hg-grid-2 hg-report-sections">
            <section class="hg-card">
                <h2 class="hg-card-title">Income</h2>
                @include('reports.partials.financial-rows', ['rows' => $report['groups']['Income'] ?? collect(), 'amountKey' => 'amount'])
                <div class="hg-report-total"><span>Total Income</span><strong>{{ \App\Support\CompanyContext::money($report['income']) }}</strong></div>
            </section>

            <section class="hg-card">
                <h2 class="hg-card-title">Expenses</h2>
                @include('reports.partials.financial-rows', ['rows' => $report['groups']['Expense'] ?? collect(), 'amountKey' => 'amount'])
                <div class="hg-report-total"><span>Total Expense</span><strong>{{ \App\Support\CompanyContext::money($report['expense']) }}</strong></div>
            </section>
        </div>

        <section class="hg-card hg-report-net-card">
            <div class="hg-report-total hg-report-total-large">
                <span>Net Profit / Loss</span>
                <strong>{{ \App\Support\CompanyContext::money($report['net_profit']) }}</strong>
            </div>
        </section>
    </div>
</x-layouts::accounting>
