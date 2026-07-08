<x-layouts::accounting title="Income Statement">
    @php
        $sections = $report['sections'];
        $sectionRows = fn (string $section) => $sections->get($section, collect());
    @endphp

    <div class="hg-report-page">
        <div class="hg-page-header hg-report-page-header">
            <div>
                <h1>Income Statement</h1>
                <p>Shows revenue, cost, expenses, and net profit from posted journals within the selected period.</p>
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

        <div class="hg-grid hg-grid-4 hg-report-summary">
            <section class="hg-card hg-metric"><span class="label">Revenue</span><div class="value">{{ \App\Support\CompanyContext::money($report['revenue']) }}</div></section>
            <section class="hg-card hg-metric"><span class="label">Gross Profit</span><div class="value">{{ \App\Support\CompanyContext::money($report['gross_profit']) }}</div></section>
            <section class="hg-card hg-metric"><span class="label">Operating Profit</span><div class="value">{{ \App\Support\CompanyContext::money($report['operating_profit']) }}</div></section>
            <section class="hg-card hg-metric"><span class="label">Net Profit / Loss</span><div class="value">{{ \App\Support\CompanyContext::money($report['net_profit']) }}</div></section>
        </div>

        <div class="hg-grid hg-grid-2 hg-report-sections">
            <section class="hg-card">
                <h2 class="hg-card-title">Revenue</h2>
                @include('reports.partials.financial-rows', ['rows' => $sectionRows('Revenue'), 'amountKey' => 'amount'])
                <div class="hg-report-total"><span>Total Revenue</span><strong>{{ \App\Support\CompanyContext::money($report['revenue']) }}</strong></div>

                <h3 class="hg-report-subtitle">Less: Cost of Sales</h3>
                @include('reports.partials.financial-rows', ['rows' => $sectionRows('Cost of Sales'), 'amountKey' => 'amount'])
                <div class="hg-report-total"><span>Gross Profit</span><strong>{{ \App\Support\CompanyContext::money($report['gross_profit']) }}</strong></div>
            </section>

            <section class="hg-card">
                <h2 class="hg-card-title">Operating Expenses</h2>

                <h3 class="hg-report-subtitle">Operating Expense</h3>
                @include('reports.partials.financial-rows', ['rows' => $sectionRows('Operating Expense'), 'amountKey' => 'amount'])

                <h3 class="hg-report-subtitle">Administrative Expense</h3>
                @include('reports.partials.financial-rows', ['rows' => $sectionRows('Administrative Expense'), 'amountKey' => 'amount'])

                <h3 class="hg-report-subtitle">Selling Expense</h3>
                @include('reports.partials.financial-rows', ['rows' => $sectionRows('Selling Expense'), 'amountKey' => 'amount'])

                <div class="hg-report-total"><span>Total Operating Expenses</span><strong>{{ \App\Support\CompanyContext::money($report['operating_expenses']) }}</strong></div>
                <div class="hg-report-total"><span>Operating Profit</span><strong>{{ \App\Support\CompanyContext::money($report['operating_profit']) }}</strong></div>
            </section>
        </div>

        <div class="hg-grid hg-grid-2 hg-report-sections">
            <section class="hg-card">
                <h2 class="hg-card-title">Other Income</h2>
                @include('reports.partials.financial-rows', ['rows' => $sectionRows('Other Income'), 'amountKey' => 'amount'])
                <div class="hg-report-total"><span>Total Other Income</span><strong>{{ \App\Support\CompanyContext::money($report['other_income']) }}</strong></div>
            </section>

            <section class="hg-card">
                <h2 class="hg-card-title">Other Expenses</h2>

                <h3 class="hg-report-subtitle">Financial Expense</h3>
                @include('reports.partials.financial-rows', ['rows' => $sectionRows('Financial Expense'), 'amountKey' => 'amount'])

                <h3 class="hg-report-subtitle">Tax Expense</h3>
                @include('reports.partials.financial-rows', ['rows' => $sectionRows('Tax Expense'), 'amountKey' => 'amount'])

                <div class="hg-report-total"><span>Net Profit Before Tax</span><strong>{{ \App\Support\CompanyContext::money($report['net_profit_before_tax']) }}</strong></div>
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
