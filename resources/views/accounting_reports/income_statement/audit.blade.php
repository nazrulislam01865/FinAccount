@extends('layouts.app')

@section('title', 'Statement of Profit or Loss and Other Comprehensive Income')

@push('styles')
    @include('accounting_reports.partials.financial-report-styles')
    <style>
        .audit-income-page .audit-statement-card {
            background: #fff;
            border: 1px solid #d8dee8;
            border-radius: 14px;
            padding: 28px;
            max-width: 980px;
            margin: 0 auto;
            box-shadow: 0 10px 30px rgba(15, 23, 42, .06);
        }

        .audit-income-page .audit-header {
            text-align: center;
            margin-bottom: 18px;
            color: #111827;
        }

        .audit-income-page .audit-company-name {
            font-size: 18px;
            font-weight: 800;
            letter-spacing: .02em;
            text-transform: uppercase;
        }

        .audit-income-page .audit-title {
            font-size: 14px;
            font-weight: 700;
            margin-top: 4px;
        }

        .audit-income-page .audit-period {
            font-size: 12px;
            font-weight: 600;
            margin-top: 2px;
        }

        .audit-income-page .audit-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 12px;
            color: #111827;
        }

        .audit-income-page .audit-table th,
        .audit-income-page .audit-table td {
            border: 1px solid #374151;
            padding: 7px 9px;
            vertical-align: middle;
        }

        .audit-income-page .audit-table th {
            text-align: center;
            font-weight: 800;
            background: #f9fafb;
        }

        .audit-income-page .audit-table .amount {
            text-align: right;
            white-space: nowrap;
            font-variant-numeric: tabular-nums;
        }

        .audit-income-page .audit-table .note-cell {
            text-align: center;
            width: 90px;
        }

        .audit-income-page .audit-table .particular-cell {
            width: 48%;
        }

        .audit-income-page .audit-section-heading td {
            border-top: 0;
            border-bottom: 0;
            font-weight: 800;
            background: #fff;
        }

        .audit-income-page .audit-total-row td {
            font-weight: 800;
            border-top: 2px solid #111827;
        }

        .audit-income-page .audit-note {
            text-align: center;
            font-size: 11px;
            margin: 16px 0 38px;
            color: #374151;
        }

        .audit-income-page .audit-signatures {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 28px;
            margin-top: 34px;
            align-items: end;
            text-align: center;
            font-size: 12px;
        }

        .audit-income-page .signature-line {
            border-top: 1px solid #111827;
            padding-top: 7px;
            font-weight: 700;
        }

        .audit-income-page .audit-footer-meta {
            display: flex;
            justify-content: space-between;
            gap: 20px;
            margin-top: 30px;
            font-size: 11px;
            color: #374151;
        }

        @media print {
            body * {
                visibility: hidden;
            }

            .audit-income-page,
            .audit-income-page * {
                visibility: visible;
            }

            .audit-income-page {
                position: absolute;
                left: 0;
                top: 0;
                width: 100%;
                padding: 0;
            }

            .audit-income-page .report-toolbar,
            .audit-income-page .report-page-header,
            .audit-income-page .print-hide {
                display: none !important;
            }

            .audit-income-page .audit-statement-card {
                border: none;
                box-shadow: none;
                padding: 10mm;
                max-width: none;
            }
        }
    </style>
@endpush

@section('content')
@php
    $formatDate = function ($date) {
        try {
            return \Illuminate\Support\Carbon::parse($date)->format('jS F, Y');
        } catch (\Throwable) {
            return (string) $date;
        }
    };

    $auditAmount = function ($amount) {
        if ($amount === null || $amount === '') {
            return '-';
        }

        $amount = (float) $amount;

        return $amount < 0
            ? '(' . number_format(abs($amount), 2) . ')'
            : number_format($amount, 2);
    };

    $periodTitle = 'For the year ended ' . $formatDate($report['to_date']);
    $companyName = $company?->company_name ?? config('app.name');
@endphp

<div class="financial-report-page audit-income-page">
    <x-report.page-header
        title="Income Statement - Audit Format"
        subtitle="Bangladesh audit-style statement format with notes and comparative year column."
    >
        <x-slot:actions>
            <a class="button btn-outline" href="{{ route('accounting-reports.income-statement.export', array_merge(request()->query(), ['statement_format' => 'audit'])) }}">⇩ Export CSV</a>
            <button class="btn-ghost" type="button" onclick="window.print()">Print</button>
            <a class="button btn-primary" href="{{ route('accounting-reports.income-statement.index', array_merge(request()->query(), ['statement_format' => 'management'])) }}">Management View</a>
        </x-slot:actions>
    </x-report.page-header>

    <form method="GET" action="{{ route('accounting-reports.income-statement.index') }}" class="card report-toolbar report-toolbar-seven accounting-filter-sequence income-template-filter print-hide">
        <input type="hidden" name="statement_format" value="audit">
        <div class="date-range-field">
            <label>Date Range</label>
            <div class="date-range-inputs">
                <input type="date" name="from_date" value="{{ $filters['from_date'] ?? $report['from_date'] }}" aria-label="From Date">
                <input type="date" name="to_date" value="{{ $filters['to_date'] ?? $report['to_date'] }}" aria-label="To Date">
            </div>
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
            <input type="text" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="Search revenue, expenses, tax...">
        </div>
        <x-report.filter-actions :reset-route="route('accounting-reports.income-statement.index', ['statement_format' => 'audit'])" submit-label="Run" />
    </form>

    <div class="audit-statement-card">
        <div class="audit-header">
            <div class="audit-company-name">{{ $companyName }}</div>
            <div class="audit-title">Statement of Profit or Loss and Other Comprehensive Income</div>
            <div class="audit-period">{{ $periodTitle }}</div>
        </div>

        <table class="audit-table">
            <thead>
                <tr>
                    <th rowspan="2" class="particular-cell">Particulars</th>
                    <th rowspan="2" class="note-cell">Notes</th>
                    <th colspan="2">Amount in Taka</th>
                </tr>
                <tr>
                    <th>{{ $report['current_period_label'] }}</th>
                    <th>{{ $report['previous_period_label'] }}</th>
                </tr>
            </thead>
            <tbody>
                @foreach($report['audit_statement'] as $row)
                    @if($row['section_heading'])
                        <tr class="audit-section-heading">
                            <td colspan="4">{{ $row['label'] }}</td>
                        </tr>
                    @else
                        <tr @class(['audit-total-row' => $row['bold']])>
                            <td>{{ $row['label'] }}</td>
                            <td class="note-cell">{{ $row['note'] }}</td>
                            <td class="amount">{{ $auditAmount($row['current']) }}</td>
                            <td class="amount">{{ $auditAmount($row['previous']) }}</td>
                        </tr>
                    @endif
                @endforeach
            </tbody>
        </table>

        <div class="audit-note">
            The annexed notes form an integral part of these Financial Statements.
        </div>

        <div class="audit-signatures">
            <div class="signature-line">Managing Director</div>
            <div class="signature-line">Director</div>
            <div class="signature-line">Director</div>
            <div class="signature-line">Auditor</div>
        </div>

        <div class="audit-footer-meta">
            <div>
                <strong>Place:</strong> {{ $company?->address ? 'Dhaka, Bangladesh' : 'Dhaka' }}<br>
                <strong>Date:</strong> {{ now()->format('d/m/Y') }}
            </div>
            <div>
                Prepared from posted accounting entries of HisebGhor Accounting System.
            </div>
        </div>
    </div>
</div>
@endsection
