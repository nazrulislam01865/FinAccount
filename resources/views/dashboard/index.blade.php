<x-layouts::accounting title="Dashboard">
    <x-slot:topbar>
        <div class="hg-topbar-title">Dashboard</div>
        <form class="hg-dashboard-period" method="GET" action="{{ route('dashboard') }}">
            <label for="dashboard-period">Period</label>
            <select id="dashboard-period" name="period" onchange="this.form.submit()">
                @foreach ($periodOptions as $value => $label)
                    <option value="{{ $value }}" @selected($period === $value)>{{ $label }}</option>
                @endforeach
            </select>
            <button type="button" onclick="window.print()">Print Dashboard</button>
        </form>
    </x-slot:topbar>

    <section class="hg-dashboard">
        <div class="hg-dashboard-hero">
            <div>
                <h1>Business Health Dashboard</h1>
            </div>
            @if(auth()->user()?->canAccounting('transactions.manage'))
            <div class="hg-dashboard-actions">
                <a class="hg-dashboard-btn blue" href="{{ route('transactions.create', ['category' => 'Sales']) }}">+ New Sale</a>
                <a class="hg-dashboard-btn green" href="{{ route('transactions.create', ['category' => 'Sales']) }}">+ Receive Money</a>
                <a class="hg-dashboard-btn red" href="{{ route('transactions.create', ['category' => 'Payment']) }}">+ Make Payment</a>
                <a class="hg-dashboard-btn light" href="{{ route('transactions.create', ['category' => 'Sales']) }}">Generate Invoice</a>
            </div>
            @endif
        </div>

        <div class="hg-dashboard-grid hg-dashboard-kpis">
            <article class="hg-dashboard-card hg-dashboard-kpi">
                <div class="hg-dashboard-kpi-title">
                    <span>Available Money</span>
                    <span class="hg-dashboard-kpi-icon soft-blue">💵</span>
                </div>
                <div class="hg-dashboard-value">{{ \App\Support\CompanyContext::money($metrics['available_money']) }}</div>
                <div class="hg-dashboard-sub">Cash + Bank + digital account balance. <span class="hg-dashboard-up">Live</span></div>
            </article>

            <article class="hg-dashboard-card hg-dashboard-kpi">
                <div class="hg-dashboard-kpi-title">
                    <span>Sales — {{ $periodLabel }}</span>
                    <span class="hg-dashboard-kpi-icon soft-green">🛒</span>
                </div>
                <div class="hg-dashboard-value">{{ \App\Support\CompanyContext::money($metrics['sales']) }}</div>
                <div class="hg-dashboard-sub">
                    Posted sales transactions.
                    @if ($metrics['sales_has_comparison'])
                        <span class="{{ $metrics['sales_change'] >= 0 ? 'hg-dashboard-up' : 'hg-dashboard-down' }}">
                            {{ $metrics['sales_change'] >= 0 ? '+' : '' }}{{ number_format($metrics['sales_change'], 1) }}%
                        </span>
                        vs previous period
                    @else
                        <span class="hg-dashboard-up">Current period</span>
                    @endif
                </div>
            </article>

            <article class="hg-dashboard-card hg-dashboard-kpi">
                <div class="hg-dashboard-kpi-title">
                    <span>Net Profit Preview</span>
                    <span class="hg-dashboard-kpi-icon soft-purple">📈</span>
                </div>
                <div class="hg-dashboard-value">{{ \App\Support\CompanyContext::money($metrics['profit']) }}</div>
                <div class="hg-dashboard-sub">Income minus expenses for {{ strtolower($periodLabel) }}. Before tax and adjustments.</div>
            </article>

            <article class="hg-dashboard-card hg-dashboard-kpi">
                <div class="hg-dashboard-kpi-title">
                    <span>Customer Due</span>
                    <span class="hg-dashboard-kpi-icon soft-amber">🤝</span>
                </div>
                <div class="hg-dashboard-value">{{ \App\Support\CompanyContext::money($metrics['customer_due']) }}</div>
                <div class="hg-dashboard-sub">
                    @if ($receivableCount > 0)
                        <span class="hg-dashboard-warn">{{ $receivableCount }} account{{ $receivableCount === 1 ? '' : 's' }} outstanding.</span> Follow-up needed.
                    @else
                        No positive customer balance is outstanding.
                    @endif
                </div>
            </article>

            <article class="hg-dashboard-card hg-dashboard-kpi">
                <div class="hg-dashboard-kpi-title">
                    <span>Payables + Loans</span>
                    <span class="hg-dashboard-kpi-icon soft-red">🏦</span>
                </div>
                <div class="hg-dashboard-value">{{ \App\Support\CompanyContext::money($metrics['payables_loans']) }}</div>
                <div class="hg-dashboard-sub">
                    Supplier payable + lender balance.
                    @if ($metrics['payables_loans'] > $metrics['available_money'])
                        <span class="hg-dashboard-down">Watch cash flow</span>
                    @else
                        <span class="hg-dashboard-up">Within available money</span>
                    @endif
                </div>
            </article>
        </div>

        <div class="hg-dashboard-grid hg-dashboard-two-col">
            <section class="hg-dashboard-card hg-dashboard-section">
                <div class="hg-dashboard-section-head">
                    <h2>Today’s Accounting Position</h2>
                    @if(auth()->user()?->canAccounting('journals.view'))
                    <a href="{{ route('journal-entries.index') }}">View ledger</a>
                    @endif
                </div>
                <div class="hg-dashboard-grid hg-dashboard-three-col">
                    <div class="hg-dashboard-formula">
                        <strong>Cash position</strong><br>
                        Opening {{ \App\Support\CompanyContext::money($position['cash']['opening']) }}
                        + Received {{ \App\Support\CompanyContext::money($position['cash']['received']) }}
                        - Paid {{ \App\Support\CompanyContext::money($position['cash']['paid']) }}
                        = <strong>{{ \App\Support\CompanyContext::money($position['cash']['closing']) }}</strong>
                    </div>
                    <div class="hg-dashboard-formula">
                        <strong>Profit preview</strong><br>
                        Income {{ \App\Support\CompanyContext::money($position['profit']['income']) }}
                        - Expense {{ \App\Support\CompanyContext::money($position['profit']['expense']) }}
                        = <strong>{{ \App\Support\CompanyContext::money($position['profit']['net']) }}</strong>
                    </div>
                    <div class="hg-dashboard-formula">
                        <strong>Owner movement</strong><br>
                        Investment {{ \App\Support\CompanyContext::money($position['owner']['investment']) }}
                        - Withdrawal {{ \App\Support\CompanyContext::money($position['owner']['withdrawal']) }}
                        = <strong>{{ \App\Support\CompanyContext::money($position['owner']['net']) }} net</strong>
                    </div>
                </div>
            </section>

            <section class="hg-dashboard-card hg-dashboard-section">
                <div class="hg-dashboard-section-head">
                    <h2>Quick Alerts</h2>
                    <span class="hg-dashboard-badge amber">{{ $alerts->count() }} item{{ $alerts->count() === 1 ? '' : 's' }}</span>
                </div>
                <div class="hg-dashboard-todo">
                    @foreach ($alerts as $alert)
                        <div class="hg-dashboard-todo-item {{ $alert['tone'] }}">
                            <div class="hg-dashboard-check">{{ $alert['icon'] }}</div>
                            <div>
                                <strong>{{ $alert['title'] }}</strong><br>
                                <span class="hg-dashboard-muted">{{ $alert['detail'] }}</span>
                            </div>
                        </div>
                    @endforeach
                </div>
            </section>
        </div>

        <div class="hg-dashboard-grid hg-dashboard-two-col hg-dashboard-block">
            <section class="hg-dashboard-card hg-dashboard-section">
                <div class="hg-dashboard-section-head">
                    <h2>Recent Transactions</h2>
                    @if(auth()->user()?->canAccounting('transactions.view'))
                    <a href="{{ route('transactions.index', ['period' => $period]) }}">Open register</a>
                    @endif
                </div>

                @if ($recentTransactions->isEmpty())
                    <div class="hg-dashboard-empty">No posted transactions were found for {{ strtolower($periodLabel) }}.</div>
                @else
                    <div class="hg-dashboard-table-wrap">
                        <table class="hg-dashboard-table">
                            <thead>
                            <tr>
                                <th>Voucher</th>
                                <th>Type</th>
                                <th>Details</th>
                                <th class="amount">Amount</th>
                                <th>Status</th>
                            </tr>
                            </thead>
                            <tbody>
                            @foreach ($recentTransactions as $transaction)
                                @php
                                    $debitAccounts = $transaction->journalEntry?->lines
                                        ?->filter(fn ($line) => (float) $line->debit > 0)
                                        ->map(fn ($line) => $line->chartOfAccount?->name)
                                        ->filter()
                                        ->join(', ');
                                    $creditAccounts = $transaction->journalEntry?->lines
                                        ?->filter(fn ($line) => (float) $line->credit > 0)
                                        ->map(fn ($line) => $line->chartOfAccount?->name)
                                        ->filter()
                                        ->join(', ');
                                @endphp
                                <tr>
                                    <td>
                                        <strong>{{ $transaction->voucher_no }}</strong><br>
                                        <span class="hg-dashboard-muted">{{ $transaction->transaction_date->format('d M') }}</span>
                                    </td>
                                    <td><span class="hg-dashboard-badge {{ strtolower($transaction->category) }}">{{ $categoryLabels[$transaction->category] ?? $transaction->category }}</span></td>
                                    <td>
                                        {{ $transaction->transactionHead?->name }}
                                        @if ($transaction->party)
                                            — {{ $transaction->party->name }}
                                        @endif
                                        <br>
                                        <span class="hg-dashboard-muted">Dr {{ $debitAccounts ?: '—' }}, Cr {{ $creditAccounts ?: '—' }}</span>
                                    </td>
                                    <td class="amount">{{ \App\Support\CompanyContext::money((float) $transaction->amount) }}</td>
                                    <td><span class="hg-dashboard-badge blue">{{ ucfirst($transaction->status) }}</span></td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </section>

            <section class="hg-dashboard-card hg-dashboard-section">
                <div class="hg-dashboard-section-head">
                    <h2>Money Accounts</h2>
                    @if(auth()->user()?->canAnyAccounting(['money_accounts.view', 'money_accounts.manage']))
                    <a href="{{ route('money-accounts.index', auth()->user()?->canAccounting('money_accounts.view') ? [] : ['action' => 'add']) }}">{{ auth()->user()?->canAccounting('money_accounts.manage') ? 'Update accounts' : 'View accounts' }}</a>
                    @endif
                </div>

                @if ($moneyAccounts->isEmpty())
                    <div class="hg-dashboard-empty">No active money account is mapped to a COA.</div>
                @else
                    <div class="hg-dashboard-cash-list">
                        @foreach ($moneyAccounts as $moneyAccount)
                            <div class="hg-dashboard-cash-row">
                                <div>
                                    <strong>{{ $moneyAccount['name'] }}</strong><br>
                                    <span class="hg-dashboard-muted">{{ $moneyAccount['description'] }}</span>
                                    <div class="hg-dashboard-progress">
                                        <div class="hg-dashboard-bar {{ $moneyAccount['bar_tone'] }}" style="width: {{ number_format($moneyAccount['progress'], 2, '.', '') }}%"></div>
                                    </div>
                                </div>
                                <strong>{{ \App\Support\CompanyContext::money($moneyAccount['balance']) }}</strong>
                            </div>
                        @endforeach
                    </div>
                @endif

                <div class="hg-dashboard-divider"></div>
                <div class="hg-dashboard-notice"><strong>Accountant note:</strong> {{ $accountantNote }}</div>
            </section>
        </div>

        <div class="hg-dashboard-grid hg-dashboard-three-col hg-dashboard-block">
            <section class="hg-dashboard-card hg-dashboard-section">
                <div class="hg-dashboard-section-head">
                    <h2>Receivables</h2>
                    @if(auth()->user()?->canAccounting('balances.view'))
                    <a href="{{ route('balances.index', ['section' => 'parties']) }}#party-balances">View parties</a>
                    @endif
                </div>
                <div class="hg-dashboard-table-wrap">
                    <table class="hg-dashboard-table">
                        <thead><tr><th>Customer</th><th class="amount">Due</th><th>Status</th></tr></thead>
                        <tbody>
                        @forelse ($receivables as $receivable)
                            <tr>
                                <td>{{ $receivable['name'] }}</td>
                                <td class="amount">{{ \App\Support\CompanyContext::money($receivable['balance']) }}</td>
                                <td><span class="hg-dashboard-badge {{ $receivable['tone'] }}">{{ $receivable['status'] }}</span></td>
                            </tr>
                        @empty
                            <tr><td colspan="3" class="hg-dashboard-empty-cell">No customer due found.</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </section>

            <section class="hg-dashboard-card hg-dashboard-section">
                <div class="hg-dashboard-section-head">
                    <h2>Payables</h2>
                    @if(auth()->user()?->canAccounting('balances.view'))
                    <a href="{{ route('balances.index', ['section' => 'parties']) }}#party-balances">View parties</a>
                    @endif
                </div>
                <div class="hg-dashboard-table-wrap">
                    <table class="hg-dashboard-table">
                        <thead><tr><th>Supplier / Lender</th><th class="amount">Balance</th><th>Status</th></tr></thead>
                        <tbody>
                        @forelse ($payables as $payable)
                            <tr>
                                <td>{{ $payable['name'] }}</td>
                                <td class="amount">{{ \App\Support\CompanyContext::money($payable['balance']) }}</td>
                                <td><span class="hg-dashboard-badge {{ $payable['tone'] }}">{{ $payable['status'] }}</span></td>
                            </tr>
                        @empty
                            <tr><td colspan="3" class="hg-dashboard-empty-cell">No supplier or lender balance found.</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </section>

            <section class="hg-dashboard-card hg-dashboard-section">
                <div class="hg-dashboard-section-head">
                    <h2>Invoice Status</h2>
                    @if(auth()->user()?->canAccounting('transactions.manage'))
                    <a href="{{ route('transactions.create', ['category' => 'Sales']) }}">Generate invoice</a>
                    @endif
                </div>
                <div class="hg-dashboard-table-wrap">
                    <table class="hg-dashboard-table">
                        <thead><tr><th>Status</th><th class="amount">Count</th><th class="amount">Amount</th></tr></thead>
                        <tbody>
                        @foreach ($invoiceStatus as $status)
                            <tr>
                                <td><span class="hg-dashboard-badge {{ $status['tone'] }}">{{ $status['label'] }}</span></td>
                                <td class="amount">{{ $status['count'] }}</td>
                                <td class="amount">{{ \App\Support\CompanyContext::money($status['amount']) }}</td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
            </section>
        </div>

        <div class="hg-dashboard-grid hg-dashboard-two-col hg-dashboard-block">
            <section class="hg-dashboard-card hg-dashboard-section">
                <div class="hg-dashboard-section-head">
                    <h2>Sales vs Expenses Trend</h2>
                    <span class="hg-dashboard-muted">Last 6 months</span>
                </div>

                @if ($trend->isEmpty())
                    <div class="hg-dashboard-empty">No trend data is available.</div>
                @else
                    <div class="hg-dashboard-chart-legend">
                        <span><i class="sales"></i> Sales</span>
                        <span><i class="expense"></i> Expenses</span>
                    </div>
                    <div class="hg-dashboard-mini-chart" aria-label="Sales and expenses monthly chart">
                        @foreach ($trend as $month)
                            <div class="hg-dashboard-month-bars">
                                <div class="hg-dashboard-mini-bar sales" style="height: {{ number_format($month['sales_height'], 2, '.', '') }}%" title="{{ $month['label'] }} sales: {{ \App\Support\CompanyContext::money($month['sales']) }}">
                                    <span>{{ number_format($month['sales'] / 1000, 1) }}k</span>
                                </div>
                                <div class="hg-dashboard-mini-bar expense" style="height: {{ number_format($month['expense_height'], 2, '.', '') }}%" title="{{ $month['label'] }} expenses: {{ \App\Support\CompanyContext::money($month['expense']) }}">
                                    <span>{{ number_format($month['expense'] / 1000, 1) }}k</span>
                                </div>
                            </div>
                        @endforeach
                    </div>
                    <div class="hg-dashboard-mini-labels">
                        @foreach ($trend as $month)
                            <span>{{ $month['label'] }}</span>
                        @endforeach
                    </div>
                @endif
            </section>

            <section class="hg-dashboard-card hg-dashboard-section">
                <div class="hg-dashboard-section-head">
                    <h2>What I Need to Decide Today</h2>
                    <span class="hg-dashboard-badge blue">Owner view</span>
                </div>
                <div class="hg-dashboard-todo">
                    @foreach ($decisions as $decision)
                        <div class="hg-dashboard-todo-item">
                            <div class="hg-dashboard-check">{{ $loop->iteration }}</div>
                            <div>
                                <strong>{{ $decision['title'] }}</strong><br>
                                <span class="hg-dashboard-muted">{{ $decision['detail'] }}</span>
                            </div>
                        </div>
                    @endforeach
                </div>
            </section>
        </div>

        <div class="hg-dashboard-grid hg-dashboard-two-col hg-dashboard-block">
            <section class="hg-dashboard-card hg-dashboard-section">
                <div class="hg-dashboard-section-head">
                    <h2>Financial Statement Snapshot</h2>
                    @if(auth()->user()?->canAccounting('statements.view'))
                    <a href="{{ route('basic-statements.index', ['section' => 'income']) }}#income-statement">Open statements</a>
                    @endif
                </div>
                <div class="hg-dashboard-table-wrap">
                    <table class="hg-dashboard-table">
                        <thead><tr><th>Statement Item</th><th class="amount">Amount</th><th>Meaning</th></tr></thead>
                        <tbody>
                        <tr><td>Revenue</td><td class="amount">{{ \App\Support\CompanyContext::money($statement['revenue']) }}</td><td>Income posted in {{ strtolower($periodLabel) }}</td></tr>
                        <tr><td>Total Expense</td><td class="amount">{{ \App\Support\CompanyContext::money($statement['expense']) }}</td><td>Expenses posted in {{ strtolower($periodLabel) }}</td></tr>
                        <tr><td>Net Profit</td><td class="amount">{{ \App\Support\CompanyContext::money($statement['net_profit']) }}</td><td>Revenue minus expense</td></tr>
                        <tr><td>Total Assets</td><td class="amount">{{ \App\Support\CompanyContext::money($statement['assets']) }}</td><td>Current account balances classified as assets</td></tr>
                        <tr><td>Total Liabilities</td><td class="amount">{{ \App\Support\CompanyContext::money($statement['liabilities']) }}</td><td>Current supplier payable and loan balances</td></tr>
                        <tr><td>Owner Equity</td><td class="amount">{{ \App\Support\CompanyContext::money($statement['equity']) }}</td><td>Capital plus accumulated profit minus drawings</td></tr>
                        </tbody>
                    </table>
                </div>
            </section>

            <section class="hg-dashboard-card hg-dashboard-section">
                <div class="hg-dashboard-section-head">
                    <h2>Dashboard Data Sources</h2>
                    <span class="hg-dashboard-badge green">Auto calculated</span>
                </div>
                <div class="hg-dashboard-formula"><strong>Sales cards</strong> come from posted sales transactions and income journals.</div>
                <div class="hg-dashboard-formula hg-dashboard-formula-gap"><strong>Money balance</strong> comes from journal lines posted to COA accounts linked with money accounts.</div>
                <div class="hg-dashboard-formula hg-dashboard-formula-gap"><strong>Customer and supplier balances</strong> come from party-linked receivable and payable journals.</div>
                <div class="hg-dashboard-formula hg-dashboard-formula-gap"><strong>Profit and balance sheet</strong> come from COA type: Asset, Liability, Income, Expense and Equity.</div>

                @if (auth()->user()?->canAccounting('settings.manage'))
                <form class="hg-dashboard-reset" method="POST" action="{{ route('dashboard.reset-demo') }}" onsubmit="return confirm('Reset all company accounting data to the sample dataset?')">
                    @csrf
                    <button type="submit">Reset Sample Data</button>
                </form>
                @endif
            </section>
        </div>

    </section>
</x-layouts::accounting>
