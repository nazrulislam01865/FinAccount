<x-layouts::accounting title="Dashboard">
    <div class="hg-page-header">
        <div>
            <h1>Accounting MVP Overview</h1>
            <p>This prototype shows how sales, payments and liability transactions can work without asking users to select debit or credit. The setup decides the journal automatically.</p>
        </div>
        <div class="hg-actions">
            <form method="POST" action="{{ route('dashboard.reset-demo') }}" onsubmit="return confirm('Reset all data to sample dataset?')">
                @csrf
                <button class="hg-btn" type="submit">Reset Sample Data</button>
            </form>
            <a class="hg-btn hg-btn-primary" href="{{ route('transactions.create', ['category' => 'Sales']) }}">+ Sale</a>
            <a class="hg-btn hg-btn-danger" href="{{ route('transactions.create', ['category' => 'Payment']) }}">+ Payment</a>
            <a class="hg-btn hg-btn-warning" href="{{ route('transactions.create', ['category' => 'Liability']) }}">+ Liability</a>
        </div>
    </div>

    <div class="hg-grid hg-grid-4">
        <article class="hg-card hg-metric">
            <div class="label">Total Sales</div>
            <div class="value">৳ {{ number_format($metrics['sales'], 2) }}</div>
            <div class="hint">Income posted from sales heads</div>
        </article>
        <article class="hg-card hg-metric">
            <div class="label">Payments</div>
            <div class="value">৳ {{ number_format($metrics['payments'], 2) }}</div>
            <div class="hint">Money out for expenses and supplier dues</div>
        </article>
        <article class="hg-card hg-metric">
            <div class="label">Liability Activity</div>
            <div class="value">৳ {{ number_format($metrics['liability'], 2) }}</div>
            <div class="hint">Loan and credit purchase activity</div>
        </article>
        <article class="hg-card hg-metric">
            <div class="label">Money Balance</div>
            <div class="value">৳ {{ number_format($metrics['money_balance'], 2) }}</div>
            <div class="hint">Cash + Bank + Digital balance</div>
        </article>
    </div>

    <div class="hg-spacer"></div>

    <section class="hg-card">
        <h2 class="hg-card-title">How the data is connected</h2>
        <div class="hg-flow">
            <div class="hg-step"><b>COA</b><span>Actual accounting ledgers</span></div>
            <div class="hg-step"><b>Money Account</b><span>Cash, bank, digital wallet</span></div>
            <div class="hg-step"><b>Party</b><span>Customer, supplier, worker, lender</span></div>
            <div class="hg-step"><b>Accounting Rule</b><span>Debit and credit source</span></div>
            <div class="hg-step"><b>Transaction Head</b><span>User-facing activity name</span></div>
            <div class="hg-step"><b>Journal</b><span>Automatic Dr/Cr posting</span></div>
        </div>
    </section>

    <div class="hg-spacer"></div>

    <div class="hg-grid hg-grid-2">
        <section class="hg-card">
            <h2 class="hg-card-title">Recent transactions</h2>
            @if ($recentTransactions->isEmpty())
                <div class="hg-empty">No transactions have been posted yet.</div>
            @else
                <div class="hg-table-wrap">
                    <table class="hg-table">
                        <thead>
                        <tr>
                            <th>Voucher</th>
                            <th>Type</th>
                            <th>Head</th>
                            <th class="right">Amount</th>
                        </tr>
                        </thead>
                        <tbody>
                        @foreach ($recentTransactions as $transaction)
                            <tr>
                                <td><b>{{ $transaction->voucher_no }}</b><br><span class="hg-muted">{{ $transaction->transaction_date->format('Y-m-d') }}</span></td>
                                <td><span class="hg-badge {{ strtolower($transaction->category) }}">{{ $transaction->category }}</span></td>
                                <td>{{ $transaction->transactionHead->name }}</td>
                                <td class="right">৳ {{ number_format((float) $transaction->amount, 2) }}</td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </section>

        <section class="hg-card">
            <h2 class="hg-card-title">Liability examples added</h2>
            <div class="hg-notice">
                <b>Feed bought on credit</b><br>
                Debit: Feed Expense. Credit: Supplier Payable.<br><br>
                <b>Loan received</b><br>
                Debit: Selected Bank/Cash. Credit: Loan Payable.<br><br>
                <b>Loan repayment</b><br>
                Debit: Loan Payable. Credit: Selected Bank/Cash.
            </div>
        </section>
    </div>
</x-layouts::accounting>
