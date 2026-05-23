@extends('layouts.app')

@section('title', 'Balance Sheet')

@push('styles')
    @include('accounting_reports.partials.financial-report-styles')
@endpush

@section('content')
@php
    $money = fn ($amount) => ($currency ?? 'BDT') . ' ' . number_format((float) $amount, 2);
    $moneyOrDash = fn ($amount) => abs((float) $amount) >= 0.01 ? $money($amount) : '—';
    $formatDate = function ($date) {
        try { return \Illuminate\Support\Carbon::parse($date)->format('d M Y'); } catch (\Throwable) { return (string) $date; }
    };
@endphp

<div class="financial-report-page">
    <x-report.page-header title="Balance Sheet" subtitle="Assets, liabilities, equity and retained profit generated from voucher detail ledger balances.">
        <x-slot:actions>
            <a class="button btn-outline" href="{{ route('accounting-reports.balance-sheet.export', request()->query()) }}">⇩ Export CSV</a>
            <button class="btn-ghost" type="button" onclick="window.print()">Print</button>
            <a class="button btn-primary" href="{{ route('accounting-reports.balance-sheet.index', request()->query()) }}">↻ Refresh</a>
        </x-slot:actions>
    </x-report.page-header>

    <div class="report-summary-grid report-summary-grid-six">
        <x-report.stat-card label="Total Assets" :value="$money($report['assets'])" tone="primary" />
        <x-report.stat-card label="Liabilities" :value="$money($report['liabilities'])" tone="warning" />
        <x-report.stat-card label="Equity" :value="$money($report['equity'])" tone="primary" />
        <x-report.stat-card label="Retained Profit/Loss" :value="$money($report['retained_profit'])" :tone="$report['retained_profit'] >= 0 ? 'success' : 'danger'" />
        <x-report.stat-card label="Liabilities + Equity" :value="$money($report['liabilities_and_equity'])" tone="primary" />
        <x-report.stat-card label="Balance Check" :value="$report['is_balanced'] ? 'Balanced' : $money(abs($report['difference']))" :tone="$report['is_balanced'] ? 'success' : 'danger'" />
    </div>

    <form method="GET" action="{{ route('accounting-reports.balance-sheet.index') }}" class="card report-toolbar report-toolbar-seven accounting-filter-sequence">
        <div>
            <label>As of Date</label>
            <input type="date" name="as_of_date" value="{{ $filters['as_of_date'] ?? $report['as_of_date'] }}">
        </div>
        <div class="field search-field">
            <label>Search Ledger</label>
            <span>⌕</span>
            <input type="text" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="Ledger code or account name...">
        </div>
        <label class="checkbox-inline"><input type="checkbox" name="include_zero_balances" value="1" @checked($filters['include_zero_balances'] ?? false)> Include zero balances</label>
        <label class="checkbox-inline"><input type="checkbox" name="include_inactive_accounts" value="1" @checked($filters['include_inactive_accounts'] ?? false)> Include inactive accounts</label>
        <x-report.filter-actions :reset-route="route('accounting-reports.balance-sheet.index')" />
    </form>

    <x-report.table-card
        title="Balance Sheet Details"
        :subtitle="'As of ' . $formatDate($report['as_of_date']) . ' · ' . $report['rows']->count() . ' ledger(s)'"
        :badge="$report['is_balanced'] ? 'Balanced' : 'Difference Found'"
        :badge-class="$report['is_balanced'] ? 'badge-success' : 'badge-warning'"
        footer-left="Phase 6 source: voucher_details joined with voucher_headers and chart_of_accounts."
        footer-right="Header amount is not used as accounting truth."
    >
        <div class="table-wrap">
            <table class="financial-table">
                <thead>
                    <tr>
                        <th>Section</th>
                        <th>Code</th>
                        <th>Ledger Account</th>
                        <th>Parent</th>
                        <th class="amount">Balance</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach(['Assets', 'Liabilities', 'Equity'] as $section)
                        @php $rows = $report['groups']->get($section, collect()); @endphp
                        <tr class="group-row"><td colspan="5">{{ $section }}</td></tr>
                        @forelse($rows as $row)
                            <tr>
                                <td><span class="badge badge-primary">{{ $row->section }}</span></td>
                                <td class="code">{{ $row->account_code }}</td>
                                <td class="strong">{{ $row->account_name }}</td>
                                <td>{{ $row->parent_account_name ?: '—' }}</td>
                                <td class="amount">{{ $moneyOrDash($row->report_balance) }}</td>
                            </tr>
                        @empty
                            <tr data-empty="true"><td colspan="5" class="empty-state">No {{ strtolower($section) }} balance found.</td></tr>
                        @endforelse
                        <tr class="total-row">
                            <td colspan="4">{{ $section }} Total</td>
                            <td class="amount">{{ $money($rows->sum('report_balance')) }}</td>
                        </tr>
                    @endforeach
                    <tr class="grand-row"><td colspan="4">Retained Profit / Loss</td><td class="amount">{{ $money($report['retained_profit']) }}</td></tr>
                    <tr class="grand-row"><td colspan="4">Balance Difference</td><td class="amount">{{ $money($report['difference']) }}</td></tr>
                </tbody>
            </table>
        </div>
    </x-report.table-card>
</div>
@endsection
