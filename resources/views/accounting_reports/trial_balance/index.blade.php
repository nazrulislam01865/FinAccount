@extends('layouts.app')

@section('title', 'Trial Balance')

@push('styles')
    @include('accounting_reports.partials.financial-report-styles')
@endpush

@section('content')
@php
    $money = fn ($amount) => ($currency ?? 'BDT') . ' ' . number_format((float) $amount, 2);
    $moneyOrDash = fn ($amount) => abs((float) $amount) >= 0.01 ? $money($amount) : '—';
    $formatDate = function ($date) {
        try {
            return \Illuminate\Support\Carbon::parse($date)->format('d M Y');
        } catch (\Throwable) {
            return (string) $date;
        }
    };
@endphp

<div class="financial-report-page">
    <div class="page-title">
        <div>
            <h2>Trial Balance</h2>
            <p>Ledger-wise debit and credit balances generated from posted voucher detail lines.</p>
        </div>
        <div class="quick-actions">
            <a class="button btn-outline" href="{{ route('accounting-reports.trial-balance.export', request()->query()) }}">⇩ Export CSV</a>
            <button class="btn-ghost" type="button" onclick="window.print()">Print</button>
            <a class="button btn-primary" href="{{ route('accounting-reports.trial-balance.index', request()->query()) }}">↻ Refresh Report</a>
        </div>
    </div>

    <div class="trial-summary-grid">
        <div class="card stat-card"><small>Total Closing Debit</small><strong>{{ $money($report['total_debit']) }}</strong></div>
        <div class="card stat-card"><small>Total Closing Credit</small><strong>{{ $money($report['total_credit']) }}</strong></div>
        <div class="card stat-card"><small>Difference</small><strong style="color:{{ $report['is_balanced'] ? '#067647' : '#dc2626' }}">{{ $money(abs($report['difference'])) }}</strong></div>
        <div class="card stat-card"><small>Report Status</small><strong style="color:{{ $report['is_balanced'] ? '#067647' : '#dc2626' }}">{{ $report['is_balanced'] ? 'Balanced' : 'Unbalanced' }}</strong></div>

        <div class="card income-info-card">
            <div class="income-info-title">Report Summary</div>
            <div class="compact-ratio-grid">
                <span>Period</span><strong>{{ $formatDate($report['from_date']) }} - {{ $formatDate($report['to_date']) }}</strong>
                <span>Basis</span><strong>Accrual</strong>
                <span>Currency</span><strong>{{ $currency ?? 'BDT' }}</strong>
                <span>Ledger Count</span><strong>{{ $report['rows']->count() }}</strong>
            </div>
        </div>

        <div class="card income-info-card">
            <div class="income-info-title">Quick Analysis</div>
            <div class="compact-ratio-grid">
                <span>Highest Debit</span><strong>{{ $report['max_debit'] ? $report['max_debit']->account_name . ' - ' . $money($report['max_debit']->closing_debit) : '—' }}</strong>
                <span>Highest Credit</span><strong>{{ $report['max_credit'] ? $report['max_credit']->account_name . ' - ' . $money($report['max_credit']->closing_credit) : '—' }}</strong>
                <span>Zero Balance</span><strong>{{ $report['zero_count'] }}</strong>
                <span>Difference</span><strong>{{ $money(abs($report['difference'])) }}</strong>
            </div>
        </div>
    </div>

    <form method="GET" action="{{ route('accounting-reports.trial-balance.index') }}" class="card report-toolbar trial">
        <div class="field search-field">
            <label>Search Ledger</label>
            <span>⌕</span>
            <input type="text" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="Ledger code or account name...">
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
            <label>Account Group</label>
            <select name="account_type">
                <option value="All">All</option>
                @foreach($report['account_types'] as $type)
                    <option value="{{ $type }}" @selected(($filters['account_type'] ?? 'All') === $type)>{{ $type }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label>Balance Type</label>
            <select name="balance_type">
                @foreach(['All', 'Debit', 'Credit', 'Zero'] as $type)
                    <option value="{{ $type }}" @selected(($filters['balance_type'] ?? 'All') === $type)>{{ $type }}</option>
                @endforeach
            </select>
        </div>
        <div class="filter-actions">
            <button class="btn-primary" type="submit">Run</button>
            <a class="button btn-ghost" href="{{ route('accounting-reports.trial-balance.index') }}">Reset</a>
        </div>
    </form>

    <div class="report-grid trial-table-full">
        <div class="card table-card">
            <div class="card-head">
                <div>
                    <h3>Ledger Balances</h3>
                    <p>{{ $formatDate($report['from_date']) }} to {{ $formatDate($report['to_date']) }} · {{ $report['rows']->count() }} ledger(s)</p>
                </div>
                <span class="badge {{ $report['is_balanced'] ? 'badge-success' : 'badge-warning' }}">{{ $report['is_balanced'] ? 'Balanced' : 'Difference Found' }}</span>
            </div>
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>Code</th>
                            <th>Ledger Account</th>
                            <th>Group</th>
                            <th style="text-align:right">Opening Debit</th>
                            <th style="text-align:right">Opening Credit</th>
                            <th style="text-align:right">Period Debit</th>
                            <th style="text-align:right">Period Credit</th>
                            <th style="text-align:right">Closing Debit</th>
                            <th style="text-align:right">Closing Credit</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($report['groups'] as $groupName => $rows)
                            @php
                                $groupOpeningDebit = $rows->sum('opening_debit');
                                $groupOpeningCredit = $rows->sum('opening_credit');
                                $groupPeriodDebit = $rows->sum('period_debit');
                                $groupPeriodCredit = $rows->sum('period_credit');
                                $groupClosingDebit = $rows->sum('closing_debit');
                                $groupClosingCredit = $rows->sum('closing_credit');
                            @endphp
                            <tr class="group-row"><td colspan="9">{{ $groupName }}</td></tr>
                            @foreach($rows as $row)
                                <tr>
                                    <td class="code">{{ $row->account_code }}</td>
                                    <td class="strong">{{ $row->account_name }}</td>
                                    <td><span class="badge badge-primary">{{ $row->account_type }}</span></td>
                                    <td class="amount">{{ $moneyOrDash($row->opening_debit) }}</td>
                                    <td class="amount">{{ $moneyOrDash($row->opening_credit) }}</td>
                                    <td class="amount">{{ $moneyOrDash($row->period_debit) }}</td>
                                    <td class="amount">{{ $moneyOrDash($row->period_credit) }}</td>
                                    <td class="amount">{{ $moneyOrDash($row->closing_debit) }}</td>
                                    <td class="amount">{{ $moneyOrDash($row->closing_credit) }}</td>
                                </tr>
                            @endforeach
                            <tr class="total-row">
                                <td colspan="3">{{ $groupName }} Total</td>
                                <td class="amount">{{ $moneyOrDash($groupOpeningDebit) }}</td>
                                <td class="amount">{{ $moneyOrDash($groupOpeningCredit) }}</td>
                                <td class="amount">{{ $moneyOrDash($groupPeriodDebit) }}</td>
                                <td class="amount">{{ $moneyOrDash($groupPeriodCredit) }}</td>
                                <td class="amount">{{ $moneyOrDash($groupClosingDebit) }}</td>
                                <td class="amount">{{ $moneyOrDash($groupClosingCredit) }}</td>
                            </tr>
                        @empty
                            <tr data-empty="true"><td colspan="9" class="empty-state">No ledger balance found for the selected filter.</td></tr>
                        @endforelse
                        <tr class="grand-row">
                            <td colspan="7">Grand Total</td>
                            <td class="amount">{{ $money($report['total_debit']) }}</td>
                            <td class="amount">{{ $money($report['total_credit']) }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <div class="table-footer">
                <span>Reports use voucher detail debit/credit rows only.</span>
                <span>Draft and cancelled vouchers are excluded.</span>
            </div>
        </div>
    </div>
    <div class="print-note">Trial Balance report printed from FinAcco Accounting System.</div>
</div>
@endsection
