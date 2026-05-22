@extends('layouts.app')

@section('title', 'Income Statement')

@push('styles')
    @include('accounting_reports.partials.financial-report-styles')
@endpush

@section('content')
@php
    $money = fn ($amount) => ($currency ?? 'BDT') . ' ' . number_format((float) $amount, 2);
    $moneySigned = fn ($amount) => ((float) $amount < 0 ? '(' . $money(abs((float) $amount)) . ')' : $money($amount));
    $formatDate = function ($date) {
        try {
            return \Illuminate\Support\Carbon::parse($date)->format('d M Y');
        } catch (\Throwable) {
            return (string) $date;
        }
    };
    $sections = ['Revenue', 'Cost of Sales', 'Operating Expenses'];
@endphp

<div class="financial-report-page">
    <div class="page-title">
        <div>
            <h2>Income Statement</h2>
            <p>Revenue, direct cost, expenses, gross profit, and net profit generated from posted journal lines.</p>
        </div>
        <div class="quick-actions">
            <a class="button btn-outline" href="{{ route('accounting-reports.income-statement.export', request()->query()) }}">⇩ Export CSV</a>
            <button class="btn-ghost" type="button" onclick="window.print()">Print</button>
            <a class="button btn-primary" href="{{ route('accounting-reports.income-statement.index', request()->query()) }}">Generate Report</a>
        </div>
    </div>

    <div class="income-summary-grid">
        <div class="card stat-card"><small>Total Revenue</small><strong style="color:#067647">{{ $money($report['revenue']) }}</strong></div>
        <div class="card stat-card"><small>Cost of Sales</small><strong style="color:#b54708">{{ $money($report['cost']) }}</strong></div>
        <div class="card stat-card"><small>Operating Expenses</small><strong style="color:#dc2626">{{ $money($report['expense']) }}</strong></div>
        <div class="card stat-card"><small>Net Profit / Loss</small><strong style="color:{{ $report['net_profit'] >= 0 ? '#2563eb' : '#dc2626' }}">{{ $moneySigned($report['net_profit']) }}</strong></div>

        <div class="card income-info-card">
            <div class="income-info-title">Report Summary</div>
            <div class="compact-ratio-grid">
                <span>Gross Margin</span><strong>{{ number_format((float) $report['gross_margin'], 2) }}%</strong>
                <span>Net Margin</span><strong>{{ number_format((float) $report['net_margin'], 2) }}%</strong>
                <span>Expense Ratio</span><strong>{{ number_format((float) $report['expense_ratio'], 2) }}%</strong>
                <span>Basis</span><strong>Accrual</strong>
            </div>
        </div>

        <div class="card income-info-card">
            <div class="income-info-title">YTD Position</div>
            <div class="compact-ratio-grid">
                <span>Revenue</span><strong>{{ $money($report['ytd_revenue']) }}</strong>
                <span>Cost</span><strong>{{ $money($report['ytd_cost']) }}</strong>
                <span>Expenses</span><strong>{{ $money($report['ytd_expense']) }}</strong>
                <span>Net Profit</span><strong>{{ $moneySigned($report['ytd_net_profit']) }}</strong>
            </div>
        </div>
    </div>

    <form method="GET" action="{{ route('accounting-reports.income-statement.index') }}" class="card report-toolbar income">
        <div class="field search-field">
            <label>Search Account</label>
            <span>⌕</span>
            <input type="text" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="Search revenue, purchase, salary, rent...">
        </div>
        <div>
            <label>From Date</label>
            <input type="date" name="from_date" value="{{ $filters['from_date'] ?? $report['from_date'] }}">
        </div>
        <div>
            <label>To Date</label>
            <input type="date" name="to_date" value="{{ $filters['to_date'] ?? $report['to_date'] }}">
        </div>
        <div>
            <label>Section</label>
            <select name="section">
                @foreach(['All', 'Revenue', 'Cost of Sales', 'Operating Expenses'] as $section)
                    <option value="{{ $section }}" @selected(($filters['section'] ?? 'All') === $section)>{{ $section }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label>Report Basis</label>
            <select name="basis">
                <option value="Accrual" selected>Accrual Basis</option>
            </select>
        </div>
        <div class="filter-actions">
            <button class="btn-primary" type="submit">Run</button>
            <a class="button btn-ghost" href="{{ route('accounting-reports.income-statement.index') }}">Reset</a>
        </div>
    </form>

    <div class="report-grid income-table-full">
        <div class="card table-card">
            <div class="card-head">
                <div>
                    <h3>Profit & Loss Statement</h3>
                    <p>{{ $formatDate($report['from_date']) }} to {{ $formatDate($report['to_date']) }} · YTD from {{ $formatDate($report['year_start']) }}</p>
                </div>
                <span class="badge {{ $report['net_profit'] >= 0 ? 'badge-success' : 'badge-warning' }}">{{ $report['net_profit'] >= 0 ? 'Profit Position' : 'Loss Position' }}</span>
            </div>
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>Particulars</th>
                            <th>Account Code</th>
                            <th>Account Type</th>
                            <th style="text-align:right">Amount</th>
                            <th style="text-align:right">YTD Amount</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($sections as $sectionName)
                            @php($rows = $report['groups']->get($sectionName, collect()))
                            @if($rows->isEmpty())
                                @continue
                            @endif
                            <tr class="group-row"><td colspan="5">{{ $sectionName }}</td></tr>
                            @foreach($rows as $row)
                                <tr>
                                    <td class="strong">{{ $row->account_name }}</td>
                                    <td class="code">{{ $row->account_code }}</td>
                                    <td><span class="badge {{ $row->account_type === 'Income' ? 'badge-success' : 'badge-warning' }}">{{ $row->account_type }}</span></td>
                                    <td class="amount">{{ $moneySigned($row->amount) }}</td>
                                    <td class="amount">{{ $moneySigned($row->ytd_amount) }}</td>
                                </tr>
                            @endforeach
                            <tr class="total-row">
                                <td colspan="3">Total {{ $sectionName }}</td>
                                <td class="amount">{{ $moneySigned($rows->sum('amount')) }}</td>
                                <td class="amount">{{ $moneySigned($rows->sum('ytd_amount')) }}</td>
                            </tr>
                            @if($sectionName === 'Cost of Sales')
                                <tr class="gross-row">
                                    <td colspan="3">Gross Profit</td>
                                    <td class="amount">{{ $moneySigned($report['gross_profit']) }}</td>
                                    <td class="amount">{{ $moneySigned($report['ytd_gross_profit']) }}</td>
                                </tr>
                            @endif
                        @empty
                            <tr data-empty="true"><td colspan="5" class="empty-state">No income or expense movement found for the selected filter.</td></tr>
                        @endforelse
                        @if($report['rows']->isEmpty())
                            <tr data-empty="true"><td colspan="5" class="empty-state">No income or expense movement found for the selected filter.</td></tr>
                        @else
                            <tr class="{{ $report['net_profit'] >= 0 ? 'profit-row' : 'loss-row' }}">
                                <td colspan="3">Net Profit / Loss</td>
                                <td class="amount">{{ $moneySigned($report['net_profit']) }}</td>
                                <td class="amount">{{ $moneySigned($report['ytd_net_profit']) }}</td>
                            </tr>
                        @endif
                    </tbody>
                </table>
            </div>
            <div class="report-note"><strong>Accounting check:</strong> this report includes Income and Expense ledger accounts only. Assets, liabilities, equity, cash, bank, receivable, and payable balances stay out of the Income Statement and appear in the Trial Balance or Balance Sheet.</div>
        </div>
    </div>
    <div class="print-note">Income Statement report printed from FinAcco Accounting System.</div>
</div>
@endsection
