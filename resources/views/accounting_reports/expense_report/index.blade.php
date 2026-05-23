@extends('layouts.app')

@section('title', 'Expense Report')

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
    <x-report.page-header title="Expense Report" subtitle="Voucher-level expense movements from voucher detail ledger lines.">
        <x-slot:actions>
            <a class="button btn-outline" href="{{ route('accounting-reports.expense-report.export', request()->query()) }}">⇩ Export CSV</a>
            <button class="btn-ghost" type="button" onclick="window.print()">Print</button>
            <a class="button btn-primary" href="{{ route('accounting-reports.expense-report.index', request()->query()) }}">↻ Refresh</a>
        </x-slot:actions>
    </x-report.page-header>

    <div class="report-summary-grid report-summary-grid-six">
        <x-report.stat-card label="Total Expense" :value="$money($report['total_amount'])" tone="danger" />
        <x-report.stat-card label="Entries" :value="$report['entry_count']" tone="muted" />
        <x-report.stat-card label="Expense Ledgers" :value="$report['by_account']->count()" tone="muted" />
        <x-report.stat-card label="Average Entry" :value="$money($report['entry_count'] ? $report['total_amount'] / max($report['entry_count'], 1) : 0)" tone="primary" />
        <x-report.info-card title="Period" :rows="[['label' => 'From', 'value' => $formatDate($report['from_date'])], ['label' => 'To', 'value' => $formatDate($report['to_date'])]]" />
        <x-report.info-card title="Source" :rows="[['label' => 'Basis', 'value' => 'Accrual'], ['label' => 'Truth', 'value' => 'voucher_details']]" />
    </div>

    <form method="GET" action="{{ route('accounting-reports.expense-report.index') }}" class="card report-toolbar report-toolbar-seven accounting-filter-sequence">
        <div class="date-range-field"><label>Date Range</label><div class="date-range-inputs"><input type="date" name="from_date" value="{{ $filters['from_date'] ?? $report['from_date'] }}"><input type="date" name="to_date" value="{{ $filters['to_date'] ?? $report['to_date'] }}"></div></div>
        <div><label>Expense Ledger</label><select name="account_id"><option value="">All Expense Ledgers</option>@foreach($report['accounts'] as $account)<option value="{{ $account->id }}" @selected((string)($filters['account_id'] ?? '') === (string)$account->id)>{{ $account->account_code }} - {{ $account->account_name }}</option>@endforeach</select></div>
        <div><label>Transaction Head</label><select name="transaction_head_id"><option value="">All Heads</option>@foreach($report['transaction_heads'] as $head)<option value="{{ $head->id }}" @selected((string)($filters['transaction_head_id'] ?? '') === (string)$head->id)>{{ $head->head_code }} - {{ $head->name }}</option>@endforeach</select></div>
        <div><label>Party</label><select name="party_id"><option value="">All Parties</option>@foreach($report['parties'] as $party)<option value="{{ $party->id }}" @selected((string)($filters['party_id'] ?? '') === (string)$party->id)>{{ $party->party_code }} - {{ $party->party_name }}</option>@endforeach</select></div>
        <div class="field search-field"><label>Search</label><span>⌕</span><input type="text" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="Voucher, ledger, party..."></div>
        <x-report.filter-actions :reset-route="route('accounting-reports.expense-report.index')" />
    </form>

    <x-report.table-card title="Expense Detail" :subtitle="$formatDate($report['from_date']) . ' to ' . $formatDate($report['to_date'])" footer-left="Expense report includes Expense account lines only." footer-right="Amount = debit minus credit.">
        <div class="table-wrap"><table class="financial-table"><thead><tr><th>Date</th><th>Voucher</th><th>Head</th><th>Party</th><th>Ledger</th><th>Reference</th><th class="amount">Debit</th><th class="amount">Credit</th><th class="amount">Expense Amount</th></tr></thead><tbody>
            @forelse($report['rows'] as $row)
                <tr><td>{{ $formatDate($row->voucher_date) }}</td><td class="code">{{ $row->voucher_number }}</td><td>{{ $row->transaction_head ?: '—' }}</td><td>{{ $row->party_name ?: '—' }}</td><td>{{ trim($row->account_code . ' - ' . $row->account_name) }}</td><td>{{ $row->reference ?: '—' }}</td><td class="amount">{{ $moneyOrDash($row->debit) }}</td><td class="amount">{{ $moneyOrDash($row->credit) }}</td><td class="amount">{{ $money($row->amount) }}</td></tr>
            @empty
                <tr data-empty="true"><td colspan="9" class="empty-state">No expense movement found.</td></tr>
            @endforelse
            <tr class="grand-row"><td colspan="8">Total Expense</td><td class="amount">{{ $money($report['total_amount']) }}</td></tr>
        </tbody></table></div>
    </x-report.table-card>
</div>
@endsection
