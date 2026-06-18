<x-layouts::accounting title="Statements">
    <div class="hg-page-header">
        <div>
            <h1>Statements View</h1>
        </div>
    </div>

    <div class="hg-grid hg-grid-3">
        <section class="hg-card" id="income-statement">
            <h2 class="hg-card-title">Income Statement</h2>
            <div class="hg-kpi-row"><span>Income</span><strong>{{ \App\Support\CompanyContext::money($statement['income']) }}</strong></div>
            <div class="hg-kpi-row"><span>Expenses</span><strong>{{ \App\Support\CompanyContext::money($statement['expense']) }}</strong></div>
            <div class="hg-kpi-row"><span>Net Profit / Loss</span><strong>{{ \App\Support\CompanyContext::money($statement['net']) }}</strong></div>
            <p class="hg-report-note">Income and expenses come from COA type.</p>
        </section>

        <section class="hg-card" id="balance-sheet">
            <h2 class="hg-card-title">Balance Sheet</h2>
            <div class="hg-kpi-row"><span>Assets</span><strong>{{ \App\Support\CompanyContext::money($statement['asset']) }}</strong></div>
            <div class="hg-kpi-row"><span>Liabilities</span><strong>{{ \App\Support\CompanyContext::money($statement['liability']) }}</strong></div>
            <div class="hg-kpi-row"><span>Owner Equity</span><strong>{{ \App\Support\CompanyContext::money($statement['equity_with_profit']) }}</strong></div>
            <p class="hg-report-note">Net profit is added to equity for this simple view.</p>
        </section>

        <section class="hg-card" id="cash-flow-statement">
            <h2 class="hg-card-title">Cash Flow Statement</h2>
            <div class="hg-kpi-row"><span>Current Money Balance</span><strong>{{ \App\Support\CompanyContext::money($statement['cash']) }}</strong></div>
            <div class="hg-kpi-row"><span>Sales Collected</span><strong>{{ \App\Support\CompanyContext::money($statement['sales_collected']) }}</strong></div>
            <div class="hg-kpi-row"><span>Payments Made</span><strong>{{ \App\Support\CompanyContext::money($statement['payments_made']) }}</strong></div>
            <p class="hg-report-note">This is a simple cash flow view calculated from money-account movements.</p>
        </section>
    </div>
</x-layouts::accounting>
