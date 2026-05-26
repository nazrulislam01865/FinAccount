@extends('layouts.app')

@section('title', 'Cash Flow Statement')

@push('styles')
    @include('accounting_reports.partials.financial-report-styles')
@endpush

@section('content')
@php
    $money = fn ($amount) => ($currency ?? 'BDT') . ' ' . number_format((float) $amount, 2);
    $moneyOrDash = fn ($amount) => abs((float) $amount) >= 0.01 ? $money($amount) : '—';
    $formatDate = function ($date) { try { return \Illuminate\Support\Carbon::parse($date)->format('d M Y'); } catch (\Throwable) { return (string) $date; } };
    $sections = ['Operating Activities', 'Investing Activities', 'Financing Activities'];
@endphp

<div class="financial-report-page">
    <x-report.page-header title="Cash Flow Statement" subtitle="Cash movement grouped into operating, investing, and financing activities from cash/bank journal line lines.">
        <x-slot:actions>
            <a class="button btn-outline" href="{{ route('accounting-reports.cash-flow-statement.export', array_merge(request()->query(), ['format' => 'xlsx'])) }}">⇩ Export XLSX</a>
            <a class="button btn-outline" href="{{ route('accounting-reports.cash-flow-statement.export', array_merge(request()->query(), ['format' => 'pdf'])) }}">⇩ Export PDF</a>
            <button class="btn-ghost" type="button" onclick="window.print()">Print</button>
            <a class="button btn-primary" href="{{ route('accounting-reports.cash-flow-statement.index', request()->query()) }}">↻ Refresh</a>
        </x-slot:actions>
    </x-report.page-header>

    <div class="report-summary-grid report-summary-grid-six">
        <x-report.stat-card label="Opening Cash" :value="$money($report['opening_cash'])" tone="primary" />
        <x-report.stat-card label="Operating" :value="$money($report['operating_cash_flow'])" tone="success" />
        <x-report.stat-card label="Investing" :value="$money($report['investing_cash_flow'])" tone="warning" />
        <x-report.stat-card label="Financing" :value="$money($report['financing_cash_flow'])" tone="primary" />
        <x-report.stat-card label="Net Cash Flow" :value="$money($report['net_cash_flow'])" :tone="$report['net_cash_flow'] >= 0 ? 'success' : 'danger'" />
        <x-report.stat-card label="Closing Cash" :value="$money($report['closing_cash'])" tone="primary" />
    </div>

    <form method="GET" action="{{ route('accounting-reports.cash-flow-statement.index') }}" class="card report-toolbar report-toolbar-seven accounting-filter-sequence">
        <div class="date-range-field">
            <label>Date Range</label>
            <div class="date-range-inputs">
                <input type="date" name="from_date" value="{{ $filters['from_date'] ?? $report['from_date'] }}">
                <input type="date" name="to_date" value="{{ $filters['to_date'] ?? $report['to_date'] }}">
            </div>
        </div>
        <div>
            <label>Section</label>
            <select name="section">
                @foreach(array_merge(['All'], $sections) as $section)
                    <option value="{{ $section }}" @selected(($filters['section'] ?? 'All') === $section)>{{ $section }}</option>
                @endforeach
            </select>
        </div>
        <x-report.filter-actions :reset-route="route('accounting-reports.cash-flow-statement.index')" />
    </form>

    <x-report.table-card title="Cash Flow Detail" :subtitle="$formatDate($report['from_date']) . ' to ' . $formatDate($report['to_date'])" footer-left="Cash flow is calculated from cash/bank debit and credit detail rows." footer-right="Classification uses contra account type heuristics.">
        <div class="table-wrap">
            <table class="financial-table">
                <thead><tr><th>Section</th><th>Date</th><th>Voucher</th><th>Cash/Bank Account</th><th>Reference</th><th class="amount">Inflow</th><th class="amount">Outflow</th><th class="amount">Net</th></tr></thead>
                <tbody>
                    @foreach($sections as $section)
                        @php $rows = $report['groups']->get($section, collect()); @endphp
                        <tr class="group-row"><td colspan="8">{{ $section }}</td></tr>
                        @forelse($rows as $row)
                            <tr>
                                <td>{{ $row->section }}</td>
                                <td>{{ $formatDate($row->voucher_date) }}</td>
                                <td class="code">{{ $row->voucher_number }}</td>
                                <td>{{ trim($row->cash_account_code . ' - ' . $row->cash_account_name) }}</td>
                                <td>{{ $row->reference ?: '—' }}</td>
                                <td class="amount">{{ $moneyOrDash($row->cash_inflow) }}</td>
                                <td class="amount">{{ $moneyOrDash($row->cash_outflow) }}</td>
                                <td class="amount">{{ $money($row->net_cash_flow) }}</td>
                            </tr>
                        @empty
                            <tr data-empty="true"><td colspan="8" class="empty-state">No {{ strtolower($section) }} movement found.</td></tr>
                        @endforelse
                        <tr class="total-row"><td colspan="7">{{ $section }} Total</td><td class="amount">{{ $money($rows->sum('net_cash_flow')) }}</td></tr>
                    @endforeach
                    <tr class="grand-row"><td colspan="7">Net Cash Flow</td><td class="amount">{{ $money($report['net_cash_flow']) }}</td></tr>
                </tbody>
            </table>
        </div>
    </x-report.table-card>
</div>
@endsection
