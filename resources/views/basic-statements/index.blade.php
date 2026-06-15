<x-layouts::accounting title="Basic Statements">
    <div class="hg-page-header">
        <div>
            <h1>Basic Statements View</h1>
            <p>A simple report preview from the same posted journals. This is not final statutory reporting, but enough to understand how records flow.</p>
        </div>
    </div>

    <div class="hg-grid hg-grid-3">
        <section class="hg-card">
            <h2 class="hg-card-title">Income Statement</h2>
            <div class="hg-kpi-row"><span>Income</span><strong>৳ {{ number_format($statement['income'], 2) }}</strong></div>
            <div class="hg-kpi-row"><span>Expenses</span><strong>৳ {{ number_format($statement['expense'], 2) }}</strong></div>
            <div class="hg-kpi-row"><span>Net Profit / Loss</span><strong>৳ {{ number_format($statement['net'], 2) }}</strong></div>
            <p class="hg-report-note">Income and expenses come from COA type.</p>
        </section>

        <section class="hg-card">
            <h2 class="hg-card-title">Balance Sheet</h2>
            <div class="hg-kpi-row"><span>Assets</span><strong>৳ {{ number_format($statement['asset'], 2) }}</strong></div>
            <div class="hg-kpi-row"><span>Liabilities</span><strong>৳ {{ number_format($statement['liability'], 2) }}</strong></div>
            <div class="hg-kpi-row"><span>Owner Equity</span><strong>৳ {{ number_format($statement['equity_with_profit'], 2) }}</strong></div>
            <p class="hg-report-note">Net profit is added to equity for this simple view.</p>
        </section>

        <section class="hg-card">
            <h2 class="hg-card-title">Cash Movement</h2>
            <div class="hg-kpi-row"><span>Current Money Balance</span><strong>৳ {{ number_format($statement['cash'], 2) }}</strong></div>
            <div class="hg-kpi-row"><span>Sales Collected</span><strong>৳ {{ number_format($statement['sales_collected'], 2) }}</strong></div>
            <div class="hg-kpi-row"><span>Payments Made</span><strong>৳ {{ number_format($statement['payments_made'], 2) }}</strong></div>
            <p class="hg-report-note">This is a simple cash movement view from money accounts.</p>
        </section>
    </div>
</x-layouts::accounting>
