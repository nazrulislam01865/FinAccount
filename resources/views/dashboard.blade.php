@extends('layouts.app')
@section('title', 'Dashboard | Accounting System')
@section('content')
@php
    $dashboard = $dashboard ?? [];
    $user = auth()->user();
    $setup = $dashboard['setup_completion'] ?? ['percent' => 0, 'completed' => 0, 'total' => 0, 'steps' => []];
    $counts = $dashboard['setup_counts'] ?? [];
    $recentTransactions = $dashboard['recent_transactions'] ?? collect();
    $currency = $currency ?? config('accounting_reports.currency', 'BDT');
    $canAddTransaction = $user?->hasAnyPermission(['transactions.create', 'transactions.draft']) ?? false;
    $canViewReports = $user?->canViewRoute('accounting-reports.index') ?? false;
    $canOpenTransactionList = $user?->canViewRoute('accounting-reports.transactions.index') ?? false;
    $canReviewApprovals = $user?->hasAnyPermission(['approvals.view', 'approvals.manage']) ?? false;
    $money = fn ($value) => $currency.' '.number_format((float) $value, 2);
    $statusClass = fn ($status) => match ($status) {
        \App\Models\VoucherHeader::STATUS_POSTED => 'badge-success',
        \App\Models\VoucherHeader::STATUS_PENDING_REVIEW => 'badge-warning',
        \App\Models\VoucherHeader::STATUS_DRAFT => 'badge-neutral',
        default => 'badge-primary',
    };
@endphp

<div class="page-title">
    <div>
        <span class="page-label">Dashboard</span>
        <h2>Business Overview</h2>
        <p>Real-time cash, bank, receivable, payable, profit/loss, setup progress, and approval status from posted accounting records.</p>
    </div>
    @if($canAddTransaction || $canViewReports)
        <div class="actions" style="border-top:0;padding-top:0">
            @if($canAddTransaction)
                <a href="{{ route('transactions.create') }}" class="button btn-primary">Add Transaction</a>
            @endif

            @if($canViewReports)
                <a href="{{ route('accounting-reports.index') }}" class="button btn-ghost">View Reports</a>
            @endif
        </div>
    @endif
</div>

<div class="stats-grid">
    <div class="card stat-card"><small>Cash In Hand</small><strong>{{ $money($dashboard['cash_in_hand'] ?? 0) }}</strong></div>
    <div class="card stat-card"><small>Bank Balance</small><strong>{{ $money($dashboard['bank_balance'] ?? 0) }}</strong></div>
    <div class="card stat-card"><small>Total Receivable</small><strong>{{ $money($dashboard['total_receivable'] ?? 0) }}</strong></div>
    <div class="card stat-card"><small>Total Payable</small><strong>{{ $money($dashboard['total_payable'] ?? 0) }}</strong></div>
</div>

<div class="stats-grid" style="margin-top:18px">
    <div class="card stat-card"><small>Monthly Income</small><strong>{{ $money($dashboard['monthly_income'] ?? 0) }}</strong></div>
    <div class="card stat-card"><small>Monthly Expense</small><strong>{{ $money($dashboard['monthly_expense'] ?? 0) }}</strong></div>
    <div class="card stat-card"><small>Net Profit / Loss</small><strong>{{ $money($dashboard['net_profit_loss'] ?? 0) }}</strong></div>
    <div class="card stat-card"><small>Pending Approvals</small><strong>{{ number_format((int) ($dashboard['pending_approvals'] ?? 0)) }}</strong></div>
</div>

<div class="layout" style="margin-top:22px">
    <div class="left-stack">
        <div class="card table-card">
            <div class="panel-head" style="padding:18px 18px 0">
                <div>
                    <h3>Recent Transactions</h3>
                    <p class="hint" style="margin-top:4px">Shows latest drafts, submitted approvals, and posted vouchers.</p>
                </div>
                @if($canOpenTransactionList)
                    <a href="{{ route('accounting-reports.transactions.index') }}" class="button btn-soft">Open List</a>
                @endif
            </div>
            <div class="table-wrap">
                <table>
                    <thead>
                    <tr>
                        <th>Date</th>
                        <th>Voucher</th>
                        <th>Head</th>
                        <th>Party</th>
                        <th>Amount</th>
                        <th>Status</th>
                    </tr>
                    </thead>
                    <tbody>
                    @forelse($recentTransactions as $voucher)
                        @php
                            $voucherDateValue = data_get($voucher, 'voucher_date');
                            try {
                                $voucherDate = $voucherDateValue instanceof \Carbon\CarbonInterface
                                    ? $voucherDateValue->format('d M Y')
                                    : ($voucherDateValue ? \Illuminate\Support\Carbon::parse($voucherDateValue)->format('d M Y') : '—');
                            } catch (\Throwable $exception) {
                                $voucherDate = '—';
                            }

                            $voucherNumber = data_get($voucher, 'voucher_number') ?: (is_string($voucher) ? $voucher : 'Draft');
                            $voucherHead = data_get($voucher, 'transactionHead.name') ?? 'Manual Entry';
                            $voucherParty = data_get($voucher, 'party.party_name') ?? '—';
                            $voucherAmount = data_get($voucher, 'amount', data_get($voucher, 'total_debit', 0));
                            $voucherStatus = data_get($voucher, 'status', 'Draft');
                        @endphp
                        <tr>
                            <td>{{ $voucherDate }}</td>
                            <td><span class="code">{{ $voucherNumber }}</span></td>
                            <td>{{ $voucherHead }}</td>
                            <td>{{ $voucherParty }}</td>
                            <td class="strong">{{ $money($voucherAmount) }}</td>
                            <td><span class="badge {{ $statusClass($voucherStatus) }}">{{ $voucherStatus }}</span></td>
                        </tr>
                    @empty
                        <tr data-empty="true">
                            <td colspan="6" class="muted">No voucher found yet. Use Add Transaction after setup is ready.</td>
                        </tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div class="card helper-card">
            <h3>Quick Actions</h3>
            <p>Use guided setup first, then let the accounting rule engine create balanced debit/credit entries automatically.</p>
            <div class="step-list">
                <div class="step-row"><div class="nav-icon done-dot">1</div><div><strong>Complete Setup</strong><small>Company, financial year, CoA, cash/bank, parties, transaction heads, rules, opening balances, and voucher numbering.</small></div></div>
                <div class="step-row"><div class="nav-icon done-dot">2</div><div><strong>Post Transactions</strong><small>Users select business transaction details; debit/credit comes from active rules.</small></div></div>
                <div class="step-row"><div class="nav-icon done-dot">3</div><div><strong>Review Reports</strong><small>Validate trial balance, cash/bank book, receivable/payable, and profit/loss after posting.</small></div></div>
            </div>
        </div>
    </div>

    <aside class="right-stack">
        <div class="card progress-card">
            <div class="progress-main">
                <div class="ring" style="--progress: {{ (int) ($setup['percent'] ?? 0) }}%"><div class="ring-inner"><strong>{{ (int) ($setup['percent'] ?? 0) }}%</strong></div></div>
                <div>
                    <h3>Setup Completion</h3>
                    <p class="hint">{{ (int) ($setup['completed'] ?? 0) }} of {{ (int) ($setup['total'] ?? 0) }} setup groups ready.</p>
                </div>
            </div>
            <div class="step-list">
                @forelse(($setup['steps'] ?? []) as $step)
                    <div class="step-row">
                        <div class="nav-icon {{ ($step['complete'] ?? false) ? 'done-dot' : '' }}">{{ ($step['complete'] ?? false) ? '✓' : '•' }}</div>
                        <div><strong>{{ $step['label'] ?? 'Setup Step' }}</strong><small>{{ ($step['complete'] ?? false) ? 'Ready' : 'Pending' }}</small></div>
                    </div>
                @empty
                    <div class="hint">Setup progress will appear after the setup service is configured.</div>
                @endforelse
            </div>
        </div>

        <div class="card info-card">
            <h3>Setup Data</h3>
            <p class="hint">Counts are used to identify missing setup before transaction posting.</p>
            <div class="step-list">
                <div class="step-row"><div class="nav-icon">FY</div><div><strong>{{ number_format($counts['financial_years'] ?? 0) }}</strong><small>Open financial years</small></div></div>
                <div class="step-row"><div class="nav-icon">COA</div><div><strong>{{ number_format($counts['posting_ledgers'] ?? 0) }}</strong><small>Posting ledgers</small></div></div>
                <div class="step-row"><div class="nav-icon">CB</div><div><strong>{{ number_format($counts['cash_bank_accounts'] ?? 0) }}</strong><small>Cash / bank accounts</small></div></div>
                <div class="step-row"><div class="nav-icon">AR</div><div><strong>{{ number_format($counts['accounting_rules'] ?? 0) }}</strong><small>Active posting rules</small></div></div>
            </div>
        </div>

        <div class="card info-card">
            <h3>Accounting Control</h3>
            <p>Dashboard totals are calculated from posted voucher lines only. Draft and pending review vouchers are visible but excluded from financial totals.</p>
            @if(($dashboard['pending_approvals'] ?? 0) > 0 && $canReviewApprovals)
                <a href="{{ route('approvals.index') }}" class="button btn-outline" style="width:100%;margin-top:12px">Review Pending Approvals</a>
            @endif
        </div>
    </aside>
</div>
@endsection
