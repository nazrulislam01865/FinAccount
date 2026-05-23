@extends('layouts.app')

@section('title', 'Customer Receivable')

@push('styles')
    @include('accounting_reports.partials.financial-report-styles')
@endpush

@section('content')
@php
    $money = fn ($amount) => ($currency ?? 'BDT') . ' ' . number_format((float) $amount, 2);
    $moneyOrDash = fn ($amount) => abs((float) $amount) >= 0.01 ? $money($amount) : '—';
    $formatDate = function ($date) { try { return \Illuminate\Support\Carbon::parse($date)->format('d M Y'); } catch (\Throwable) { return (string) $date; } };
@endphp

<div class="financial-report-page">
    <x-report.page-header title="Customer Receivable" subtitle="Customer-wise receivable opening, movement, and closing balance from party-control voucher details.">
        <x-slot:actions>
            <a class="button btn-outline" href="{{ route('accounting-reports.customer-receivables.export', request()->query()) }}">⇩ Export CSV</a>
            <button class="btn-ghost" type="button" onclick="window.print()">Print</button>
            <a class="button btn-primary" href="{{ route('accounting-reports.customer-receivables.index', request()->query()) }}">↻ Refresh</a>
        </x-slot:actions>
    </x-report.page-header>

    <div class="report-summary-grid report-summary-grid-six">
        <x-report.stat-card label="Opening Receivable" :value="$money($report['total_opening'])" tone="primary" />
        <x-report.stat-card label="Debit Movement" :value="$money($report['total_debit_movement'])" tone="primary" />
        <x-report.stat-card label="Credit Movement" :value="$money($report['total_credit_movement'])" tone="warning" />
        <x-report.stat-card label="Closing Receivable" :value="$money($report['total_closing'])" :tone="$report['total_closing'] >= 0 ? 'success' : 'danger'" />
        <x-report.stat-card label="Customer Count" :value="$report['rows']->pluck('party_id')->unique()->count()" tone="muted" />
        <x-report.stat-card label="Ledger Lines" :value="$report['rows']->count()" tone="muted" />
    </div>

    <form method="GET" action="{{ route('accounting-reports.customer-receivables.index') }}" class="card report-toolbar report-toolbar-seven accounting-filter-sequence">
        <div class="date-range-field"><label>Date Range</label><div class="date-range-inputs"><input type="date" name="from_date" value="{{ $filters['from_date'] ?? $report['from_date'] }}"><input type="date" name="to_date" value="{{ $filters['to_date'] ?? $report['to_date'] }}"></div></div>
        <div><label>Customer</label><select name="party_id"><option value="">All Customers</option>@foreach($report['parties'] as $party)<option value="{{ $party->id }}" @selected((string)($filters['party_id'] ?? '') === (string)$party->id)>{{ $party->party_code }} - {{ $party->party_name }}</option>@endforeach</select></div>
        <div class="field search-field"><label>Search</label><span>⌕</span><input type="text" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="Customer or ledger..."></div>
        <label class="checkbox-inline"><input type="checkbox" name="include_zero_balances" value="1" @checked($filters['include_zero_balances'] ?? false)> Include zero balances</label>
        <x-report.filter-actions :reset-route="route('accounting-reports.customer-receivables.index')" />
    </form>

    <x-report.table-card title="Receivable Ledger" :subtitle="$formatDate($report['from_date']) . ' to ' . $formatDate($report['to_date'])" footer-left="Customer receivable comes from party-control debit minus credit movements." footer-right="Header amount is not used.">
        <div class="table-wrap"><table class="financial-table"><thead><tr><th>Customer Code</th><th>Customer</th><th>Ledger</th><th class="amount">Opening</th><th class="amount">Debit</th><th class="amount">Credit</th><th class="amount">Closing</th></tr></thead><tbody>
            @forelse($report['rows'] as $row)
                <tr><td class="code">{{ $row->party_code }}</td><td class="strong">{{ $row->party_name }}</td><td>{{ trim(($row->account_code ? $row->account_code . ' - ' : '') . $row->account_name) }}</td><td class="amount">{{ $moneyOrDash($row->opening_balance) }}</td><td class="amount">{{ $moneyOrDash($row->debit_movement) }}</td><td class="amount">{{ $moneyOrDash($row->credit_movement) }}</td><td class="amount">{{ $money($row->closing_balance) }}</td></tr>
            @empty
                <tr data-empty="true"><td colspan="7" class="empty-state">No customer receivable balance found.</td></tr>
            @endforelse
            <tr class="grand-row"><td colspan="3">Grand Total</td><td class="amount">{{ $money($report['total_opening']) }}</td><td class="amount">{{ $money($report['total_debit_movement']) }}</td><td class="amount">{{ $money($report['total_credit_movement']) }}</td><td class="amount">{{ $money($report['total_closing']) }}</td></tr>
        </tbody></table></div>
    </x-report.table-card>
</div>
@endsection
