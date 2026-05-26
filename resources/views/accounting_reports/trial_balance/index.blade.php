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
    $summaryRows = [
        ['label' => 'Period', 'value' => $formatDate($report['from_date']) . ' - ' . $formatDate($report['to_date'])],
        ['label' => 'Basis', 'value' => 'Accrual'],
        ['label' => 'Currency', 'value' => $currency ?? 'BDT'],
        ['label' => 'Ledger Count', 'value' => (string) $report['rows']->count()],
    ];
    $analysisRows = [
        ['label' => 'Highest Debit', 'value' => $report['max_debit'] ? $report['max_debit']->account_name . ' - ' . $money($report['max_debit']->closing_debit) : '—'],
        ['label' => 'Highest Credit', 'value' => $report['max_credit'] ? $report['max_credit']->account_name . ' - ' . $money($report['max_credit']->closing_credit) : '—'],
        ['label' => 'Zero Balance', 'value' => (string) $report['zero_count']],
        ['label' => 'Difference', 'value' => $money(abs($report['difference']))],
    ];
@endphp

<div class="financial-report-page">
    <x-report.page-header
        title="Trial Balance"
        subtitle="Ledger-wise debit and credit balances generated from posted journal line lines."
    >
        <x-slot:actions>
            <a class="button btn-outline" href="{{ route('accounting-reports.trial-balance.export', array_merge(request()->query(), ['format' => 'xlsx'])) }}">⇩ Export XLSX</a>
            <a class="button btn-outline" href="{{ route('accounting-reports.trial-balance.export', array_merge(request()->query(), ['format' => 'pdf'])) }}">⇩ Export PDF</a>
            <button class="btn-ghost" type="button" onclick="window.print()">Print</button>
            <a class="button btn-primary" href="{{ route('accounting-reports.trial-balance.index', request()->query()) }}">↻ Refresh Report</a>
        </x-slot:actions>
    </x-report.page-header>

    <div class="report-summary-grid report-summary-grid-six">
        <x-report.stat-card label="Total Closing Debit" :value="$money($report['total_debit'])" tone="primary" />
        <x-report.stat-card label="Total Closing Credit" :value="$money($report['total_credit'])" tone="primary" />
        <x-report.stat-card label="Difference" :value="$money(abs($report['difference']))" :tone="$report['is_balanced'] ? 'success' : 'danger'" />
        <x-report.stat-card label="Report Status" :value="$report['is_balanced'] ? 'Balanced' : 'Unbalanced'" :tone="$report['is_balanced'] ? 'success' : 'danger'" />
        <x-report.info-card title="Report Summary" :rows="$summaryRows" />
        <x-report.info-card title="Quick Analysis" :rows="$analysisRows" />
    </div>

    <form method="GET" action="{{ route('accounting-reports.trial-balance.index') }}" class="card report-toolbar report-toolbar-seven accounting-filter-sequence">
        <div class="date-range-field">
            <label>Date Range</label>
            <div class="date-range-inputs">
                <input type="date" name="from_date" value="{{ $filters['from_date'] ?? $report['from_date'] }}" aria-label="From Date">
                <input type="date" name="to_date" value="{{ $filters['to_date'] ?? $report['to_date'] }}" aria-label="To Date">
            </div>
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
        <div class="field search-field">
            <label>Search Ledger</label>
            <span>⌕</span>
            <input type="text" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="Ledger code or account name...">
        </div>
        <x-report.filter-actions :reset-route="route('accounting-reports.trial-balance.index')" />
    </form>

    <div class="report-grid report-grid-full">
        <x-report.table-card
            title="Ledger Balances"
            :subtitle="$formatDate($report['from_date']) . ' to ' . $formatDate($report['to_date']) . ' · ' . $report['rows']->count() . ' ledger(s)'"
            :badge="$report['is_balanced'] ? 'Balanced' : 'Difference Found'"
            :badge-class="$report['is_balanced'] ? 'badge-success' : 'badge-warning'"
            footer-left="Reports use posted journal_lines only."
            footer-right="Draft and cancelled vouchers are excluded."
        >
            <div class="table-wrap">
                <table class="financial-table trial-table">
                    <thead>
                        <tr>
                            <th>Code</th>
                            <th>Ledger Account</th>
                            <th>Group</th>
                            <th class="amount">Opening Debit</th>
                            <th class="amount">Opening Credit</th>
                            <th class="amount">Period Debit</th>
                            <th class="amount">Period Credit</th>
                            <th class="amount">Closing Debit</th>
                            <th class="amount">Closing Credit</th>
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
        </x-report.table-card>
    </div>
    <div class="print-note">Trial Balance report printed from FinAcco Accounting System.</div>
</div>
@endsection
