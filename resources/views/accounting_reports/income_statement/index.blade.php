@extends('layouts.app')

@section('title', 'Income Statement')

@push('styles')
    @include('accounting_reports.partials.financial-report-styles')
@endpush

@section('content')
@php
    $money = fn ($amount) => ($currency ?? 'BDT') . ' ' . number_format((float) $amount, 2);
    $moneySigned = fn ($amount) => ((float) $amount < 0 ? '(' . $money(abs((float) $amount)) . ')' : $money($amount));
    $moneyShort = fn ($amount) => ($currency ?? 'BDT') . ' ' . number_format((float) $amount, 0);
    $formatDate = function ($date) {
        try {
            return \Illuminate\Support\Carbon::parse($date)->format('d M Y');
        } catch (\Throwable) {
            return (string) $date;
        }
    };

    $sections = ['Revenue', 'Cost of Services', 'Administrative & Selling Expenses', 'Financial Expenses', 'Other Income / Loss', 'Income Tax Expense'];
    $selectedSection = $filters['section'] ?? 'All';
    $visibleSections = $selectedSection === 'All' ? $sections : [$selectedSection];
    $isProfit = (float) $report['net_profit'] >= 0;
    $basis = $filters['basis'] ?? 'Accrual';
    $maxBarBase = max(abs((float) $report['revenue']), 1);
    $revenueBar = abs((float) $report['revenue']) > 0 ? 100 : 0;
    $costBar = min(100, (abs((float) $report['cost']) / $maxBarBase) * 100);
    $expenseBar = min(100, (abs((float) $report['expense']) / $maxBarBase) * 100);
    $periodText = $formatDate($report['from_date']) . ' to ' . $formatDate($report['to_date']);
    $ytdText = 'YTD from ' . $formatDate($report['year_start']);
    $totalRows = $report['rows']->count();

    $reportSummaryRows = [
        ['label' => 'Gross Profit Margin', 'value' => number_format((float) $report['gross_margin'], 2) . '%'],
        ['label' => 'Net Profit Margin', 'value' => number_format((float) $report['net_margin'], 2) . '%'],
        ['label' => 'Expense to Revenue', 'value' => number_format((float) $report['expense_ratio'], 2) . '%'],
        ['label' => 'Report Basis', 'value' => $basis],
    ];

    $ytdRows = [
        ['label' => 'Revenue', 'value' => $money($report['ytd_revenue'])],
        ['label' => 'Cost of Services', 'value' => $money($report['ytd_cost'])],
        ['label' => 'Expenses', 'value' => $money($report['ytd_expense'])],
        ['label' => 'Net Profit / Loss', 'value' => $moneySigned($report['ytd_net_profit'])],
    ];
@endphp

<div class="financial-report-page income-statement-page">
    <x-report.page-header
        title="Income Statement"
        subtitle="Review revenue, cost, expenses, tax, and net profit for the selected period. The report is generated from posted accounting lines only."
    >
        <x-slot:actions>
            <a class="button btn-outline" href="{{ route('accounting-reports.income-statement.export', array_merge(request()->query(), ['statement_format' => 'management'])) }}">⇩ Export CSV</a>
            <button class="btn-ghost" type="button" onclick="window.print()">Print</button>
            <a class="button btn-primary" href="{{ route('accounting-reports.income-statement.index', request()->query()) }}">Generate Report</a>
        </x-slot:actions>
    </x-report.page-header>

    <div class="report-summary-grid income-template-stats">
        <x-report.stat-card label="Total Revenue" :value="$money($report['revenue'])" note="Income posted in this period" tone="success" />
        <x-report.stat-card label="Cost of Services" :value="$money($report['cost'])" note="Direct cost / purchase cost" tone="warning" />
        <x-report.stat-card label="Administrative & Selling Expenses" :value="$money($report['expense'])" note="Expense ledgers only" tone="danger" />
        <x-report.stat-card label="Net Profit / Loss" :value="$moneySigned($report['net_profit'])" :note="$isProfit ? 'After cost and expenses' : 'Loss after cost and expenses'" :tone="$isProfit ? 'primary' : 'danger'" />
    </div>

    <form method="GET" action="{{ route('accounting-reports.income-statement.index') }}" class="card report-toolbar report-toolbar-seven accounting-filter-sequence income-template-filter">
        <div class="date-range-field">
            <label>Date Range</label>
            <div class="date-range-inputs">
                <input type="date" name="from_date" value="{{ $filters['from_date'] ?? $report['from_date'] }}" aria-label="From Date">
                <input type="date" name="to_date" value="{{ $filters['to_date'] ?? $report['to_date'] }}" aria-label="To Date">
            </div>
        </div>
        <div>
            <label>Section</label>
            <select name="section">
                @foreach(['All', 'Revenue', 'Cost of Services', 'Administrative & Selling Expenses', 'Financial Expenses', 'Other Income / Loss', 'Income Tax Expense'] as $section)
                    <option value="{{ $section }}" @selected(($filters['section'] ?? 'All') === $section)>{{ $section }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label>Statement Format</label>
            <select name="statement_format">
                <option value="management" @selected(($filters['statement_format'] ?? 'management') === 'management')>Management View</option>
                <option value="audit" @selected(($filters['statement_format'] ?? 'management') === 'audit')>Audit Format</option>
            </select>
        </div>
        <div>
            <label>Report Basis</label>
            <select name="basis">
                <option value="Accrual" selected>Accrual Basis</option>
            </select>
        </div>
        <div class="field search-field">
            <label>Search Account</label>
            <span>⌕</span>
            <input type="text" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="Search revenue, cost, salary, rent...">
        </div>
        <x-report.filter-actions :reset-route="route('accounting-reports.income-statement.index')" submit-label="Run" />
    </form>

    <div class="report-grid income-template-layout">
        <x-report.table-card
            title="Profit & Loss Statement"
            :subtitle="$periodText . ' · ' . $ytdText"
            :badge="$isProfit ? 'Profit Position' : 'Loss Position'"
            :badge-class="$isProfit ? 'badge-success' : 'badge-warning'"
            class="income-template-card"
        >
            <div class="table-wrap">
                <table id="incomeStatementTable" class="financial-table income-table income-statement-template-table" data-client-pagination="true" data-page-size="10">
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
                        @foreach($visibleSections as $sectionName)
                            @php($rows = $report['groups']->get($sectionName, collect()))
                            <tr class="group-row section-row"><td colspan="5">{{ $sectionName }}</td></tr>
                            @forelse($rows as $row)
                                <tr>
                                    <td class="strong">{{ $row->account_name }}</td>
                                    <td class="code">{{ $row->account_code }}</td>
                                    <td>
                                        <span class="badge {{ $row->account_type === 'Income' ? 'badge-success' : ($sectionName === 'Cost of Services' ? 'badge-warning' : 'badge-danger') }}">
                                            {{ $sectionName === 'Cost of Services' ? 'Cost of Services' : $row->account_type }}
                                        </span>
                                    </td>
                                    <td class="amount">{{ $moneySigned($row->amount) }}</td>
                                    <td class="amount">{{ $moneySigned($row->ytd_amount) }}</td>
                                </tr>
                            @empty
                                <tr class="empty-row"><td colspan="5">No posted {{ strtolower($sectionName) }} movement found for the selected filter.</td></tr>
                            @endforelse
                            <tr class="total-row">
                                <td colspan="3">Total {{ $sectionName }}</td>
                                <td class="amount">{{ $moneySigned($rows->sum('amount')) }}</td>
                                <td class="amount">{{ $moneySigned($rows->sum('ytd_amount')) }}</td>
                            </tr>
                            @if($sectionName === 'Cost of Services' || ($selectedSection === 'All' && $sectionName === 'Revenue' && ! $report['groups']->has('Cost of Services')))
                                <tr class="gross-row">
                                    <td colspan="3">Gross Profit</td>
                                    <td class="amount">{{ $moneySigned($report['gross_profit']) }}</td>
                                    <td class="amount">{{ $moneySigned($report['ytd_gross_profit']) }}</td>
                                </tr>
                            @endif
                        @endforeach

                        @if($selectedSection === 'All')
                            <tr class="{{ $isProfit ? 'profit-row' : 'loss-row' }}">
                                <td colspan="3">Net {{ $isProfit ? 'Profit' : 'Loss' }}</td>
                                <td class="amount">{{ $moneySigned($report['net_profit']) }}</td>
                                <td class="amount">{{ $moneySigned($report['ytd_net_profit']) }}</td>
                            </tr>
                        @endif
                    </tbody>
                </table>
            </div>
            <div class="report-note">
                <strong>Accounting check:</strong> Income Statement includes only Income and Expense class ledgers. Cash, bank, receivable, payable, asset, liability, and equity ledgers are intentionally excluded and remain in Trial Balance / Balance Sheet.
            </div>
        </x-report.table-card>

        <aside class="report-side-stack">
            <div class="card side-card">
                <h3>Report Summary</h3>
                @foreach($reportSummaryRows as $row)
                    <div class="ratio-row"><span>{{ $row['label'] }}</span><strong>{{ $row['value'] }}</strong></div>
                @endforeach
                <div class="mini-chart" aria-label="Income statement amount comparison">
                    <div class="bar-item">
                        <span><b>Revenue</b><em>{{ $moneyShort($report['revenue']) }}</em></span>
                        <div class="bar"><div class="bar-fill green-bg" style="width: {{ $revenueBar }}%"></div></div>
                    </div>
                    <div class="bar-item">
                        <span><b>Cost</b><em>{{ $moneyShort($report['cost']) }}</em></span>
                        <div class="bar"><div class="bar-fill orange-bg" style="width: {{ $costBar }}%"></div></div>
                    </div>
                    <div class="bar-item">
                        <span><b>Expense</b><em>{{ $moneyShort($report['expense']) }}</em></span>
                        <div class="bar"><div class="bar-fill" style="width: {{ $expenseBar }}%"></div></div>
                    </div>
                </div>
            </div>

            <div class="card side-card">
                <h3>YTD Position</h3>
                @foreach($ytdRows as $row)
                    <div class="ratio-row"><span>{{ $row['label'] }}</span><strong>{{ $row['value'] }}</strong></div>
                @endforeach
            </div>

            <div class="card side-card">
                <h3>Accounting Guardrails</h3>
                <div class="guardrail-list">
                    <div class="guardrail-item"><span>✓</span><p>Report reads posted voucher details as journal-line equivalent source records.</p></div>
                    <div class="guardrail-item"><span>✓</span><p>Income is calculated as Credit − Debit; Expense is calculated as Debit − Credit.</p></div>
                    <div class="guardrail-item"><span>✓</span><p>Due collection / supplier payment rules do not recognize income or expense again.</p></div>
                </div>
            </div>

            <div class="card side-card">
                <h3>Quick Notes</h3>
                <div class="insight">
                    <div class="insight-icon">💡</div>
                    <div><strong>Rule-driven posting</strong><p>Users record a transaction head and amount; the accounting rule creates the debit and credit lines used by this report.</p></div>
                </div>
                <div class="insight">
                    <div class="insight-icon">✓</div>
                    <div><strong>Clean report logic</strong><p>Only revenue, cost, and operating expense ledgers appear here. Balance Sheet accounts stay outside the Income Statement.</p></div>
                </div>
            </div>
        </aside>
    </div>

    <div class="print-note">Income Statement report printed from HisebGhor Accounting System. Period: {{ $periodText }}.</div>
</div>
@endsection
