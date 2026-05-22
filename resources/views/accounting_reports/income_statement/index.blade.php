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
    $reportSummaryRows = [
        ['label' => 'Gross Margin', 'value' => number_format((float) $report['gross_margin'], 2) . '%'],
        ['label' => 'Net Margin', 'value' => number_format((float) $report['net_margin'], 2) . '%'],
        ['label' => 'Expense Ratio', 'value' => number_format((float) $report['expense_ratio'], 2) . '%'],
        ['label' => 'Basis', 'value' => 'Accrual'],
    ];
    $ytdRows = [
        ['label' => 'Revenue', 'value' => $money($report['ytd_revenue'])],
        ['label' => 'Cost', 'value' => $money($report['ytd_cost'])],
        ['label' => 'Expenses', 'value' => $money($report['ytd_expense'])],
        ['label' => 'Net Profit', 'value' => $moneySigned($report['ytd_net_profit'])],
    ];
@endphp

<div class="financial-report-page">
    <x-report.page-header
        title="Income Statement"
        subtitle="Revenue, direct cost, expenses, gross profit, and net profit generated from posted journal lines."
    >
        <x-slot:actions>
            <a class="button btn-outline" href="{{ route('accounting-reports.income-statement.export', request()->query()) }}">⇩ Export CSV</a>
            <button class="btn-ghost" type="button" onclick="window.print()">Print</button>
            <a class="button btn-primary" href="{{ route('accounting-reports.income-statement.index', request()->query()) }}">Generate Report</a>
        </x-slot:actions>
    </x-report.page-header>

    <div class="report-summary-grid report-summary-grid-six">
        <x-report.stat-card label="Total Revenue" :value="$money($report['revenue'])" tone="success" />
        <x-report.stat-card label="Cost of Sales" :value="$money($report['cost'])" tone="warning" />
        <x-report.stat-card label="Operating Expenses" :value="$money($report['expense'])" tone="danger" />
        <x-report.stat-card label="Net Profit / Loss" :value="$moneySigned($report['net_profit'])" :tone="$report['net_profit'] >= 0 ? 'primary' : 'danger'" />
        <x-report.info-card title="Report Summary" :rows="$reportSummaryRows" />
        <x-report.info-card title="YTD Position" :rows="$ytdRows" />
    </div>

    <form method="GET" action="{{ route('accounting-reports.income-statement.index') }}" class="card report-toolbar report-toolbar-seven">
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
        <x-report.filter-actions :reset-route="route('accounting-reports.income-statement.index')" />
    </form>

    <div class="report-grid report-grid-full">
        <x-report.table-card
            title="Profit & Loss Statement"
            :subtitle="$formatDate($report['from_date']) . ' to ' . $formatDate($report['to_date']) . ' · YTD from ' . $formatDate($report['year_start'])"
            :badge="$report['net_profit'] >= 0 ? 'Profit Position' : 'Loss Position'"
            :badge-class="$report['net_profit'] >= 0 ? 'badge-success' : 'badge-warning'"
        >
            <div class="table-wrap">
                <table class="financial-table income-table">
                    <thead>
                        <tr>
                            <th>Particulars</th>
                            <th>Account Code</th>
                            <th>Account Type</th>
                            <th class="amount">Amount</th>
                            <th class="amount">YTD Amount</th>
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
        </x-report.table-card>
    </div>
    <div class="print-note">Income Statement report printed from FinAcco Accounting System.</div>
</div>
@endsection
