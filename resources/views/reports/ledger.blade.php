@extends('layouts.app')

@section('title', 'Ledger Report | Accounting System')

@section('content')
@php
    $money = fn ($value) => 'BDT ' . number_format((float) $value, 2);
    $dateLabel = fn ($date) => $date ? \Carbon\Carbon::parse($date)->format('d M Y') : '-';
@endphp

<div class="page-title">
    <div>
        <span class="page-label">Financial Report</span>
        <h2>Ledger Report</h2>
        <p>Account-wise debit, credit, and running balance. This report is generated from posted voucher details.</p>
    </div>
    <div class="quick-actions">
        <button class="btn-outline" type="button" onclick="window.print()">Print</button>
        <button class="btn-ghost" type="button" data-toast="Excel export can be added after report finalization.">Export</button>
    </div>
</div>

<form class="card toolbar ledger-toolbar" method="GET" action="{{ route('ledger-report.index') }}">
    <div>
        <label>Ledger Account</label>
        <select name="account_id" required>
            @foreach($accounts as $ledgerAccount)
                <option value="{{ $ledgerAccount->id }}" @selected($account && $account->id === $ledgerAccount->id)>
                    {{ $ledgerAccount->account_code }} - {{ $ledgerAccount->account_name }}
                </option>
            @endforeach
        </select>
    </div>
    <div>
        <label>From Date</label>
        <input type="date" name="from_date" value="{{ $fromDate }}" required>
    </div>
    <div>
        <label>To Date</label>
        <input type="date" name="to_date" value="{{ $toDate }}" required>
    </div>
    <button class="btn-primary" type="submit">Run Report</button>
</form>

<div class="stats-grid ledger-stats" style="margin-top:18px">
    <div class="card stat-card">
        <small>Opening Balance</small>
        <strong class="orange-text">{{ $report['opening_balance_label'] }}</strong>
        <span class="muted">Before {{ $dateLabel($fromDate) }}</span>
    </div>
    <div class="card stat-card">
        <small>Total Debit</small>
        <strong class="green-text">{{ $money($report['total_debit']) }}</strong>
        <span class="muted">Within selected period</span>
    </div>
    <div class="card stat-card">
        <small>Total Credit</small>
        <strong class="red-text">{{ $money($report['total_credit']) }}</strong>
        <span class="muted">Within selected period</span>
    </div>
    <div class="card stat-card">
        <small>Closing Balance</small>
        <strong>{{ $report['closing_balance_label'] }}</strong>
        <span class="muted">Running ending balance</span>
    </div>
</div>

<div class="layout ledger-layout" style="margin-top:18px">
    <div class="left-stack">
        <div class="card table-card ledger-report-card">
            <div class="card-head">
                <div>
                    <h3>{{ $account?->display_name ?? 'Ledger Account' }}</h3>
                    <p>{{ $dateLabel($fromDate) }} to {{ $dateLabel($toDate) }}. Draft vouchers are excluded.</p>
                </div>
                <span class="badge badge-primary">{{ $report['total_entries'] }} entries</span>
            </div>
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Voucher</th>
                            <th>Type</th>
                            <th>Particulars</th>
                            <th>Party</th>
                            <th>Reference</th>
                            <th>Debit</th>
                            <th>Credit</th>
                            <th>Running Balance</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr class="opening-row">
                            <td colspan="6" class="strong">Opening Balance</td>
                            <td class="money-cell">{{ $report['opening_debit'] ? $money($report['opening_debit']) : '-' }}</td>
                            <td class="money-cell">{{ $report['opening_credit'] ? $money($report['opening_credit']) : '-' }}</td>
                            <td class="money-cell strong">{{ $report['opening_balance_label'] }}</td>
                        </tr>
                        @forelse($report['rows'] as $row)
                            <tr>
                                <td>{{ $dateLabel($row['date']) }}</td>
                                <td class="strong">{{ $row['voucher_number'] }}</td>
                                <td><span class="badge badge-neutral">{{ $row['voucher_type'] }}</span></td>
                                <td>
                                    <strong>{{ $row['transaction_head'] ?? '-' }}</strong><br>
                                    <span class="muted">{{ $row['settlement_type'] ?? '' }} {{ $row['narration'] ? ' | ' . $row['narration'] : '' }}</span>
                                </td>
                                <td>{{ $row['party_name'] ?: '-' }}</td>
                                <td>{{ $row['reference'] ?: '-' }}</td>
                                <td class="money-cell debit-text">{{ $row['debit'] ? $money($row['debit']) : '-' }}</td>
                                <td class="money-cell credit-text">{{ $row['credit'] ? $money($row['credit']) : '-' }}</td>
                                <td class="money-cell strong">{{ $row['running_balance_label'] }}</td>
                            </tr>
                        @empty
                            <tr data-empty="true">
                                <td colspan="9" class="empty-state">No posted ledger movement found for this account and date range.</td>
                            </tr>
                        @endforelse
                        <tr class="total-row">
                            <td colspan="6" class="strong">Period Total</td>
                            <td class="money-cell strong">{{ $money($report['total_debit']) }}</td>
                            <td class="money-cell strong">{{ $money($report['total_credit']) }}</td>
                            <td></td>
                        </tr>
                        <tr class="closing-row">
                            <td colspan="8" class="strong">Closing Balance</td>
                            <td class="money-cell strong">{{ $report['closing_balance_label'] }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <div class="table-footer">
                <span>Ledger report reads posted voucher_details only.</span>
                <span>Normal Balance: {{ $report['normal_balance'] }}</span>
            </div>
        </div>
    </div>

    <aside class="right-stack">
        <div class="card helper-card">
            <h3>Report Summary</h3>
            <div class="info-list">
                <div class="info-row"><span>Account Type</span><strong>{{ $report['account_type'] }}</strong></div>
                <div class="info-row"><span>Normal Balance</span><strong>{{ $report['normal_balance'] }}</strong></div>
                <div class="info-row"><span>Total Entries</span><strong>{{ $report['total_entries'] }}</strong></div>
                <div class="info-row"><span>Last Voucher</span><strong>{{ $report['last_transaction'] }}</strong></div>
            </div>
        </div>
        <div class="card helper-card">
            <h3>Accounting Rule</h3>
            <p>Ledger is not stored separately. It is generated from voucher details linked to posted voucher headers.</p>
            <p class="muted" style="margin-top:10px">Debit-normal accounts use Dr - Cr. Credit-normal accounts use Cr - Dr for accounting balance.</p>
        </div>
    </aside>
</div>
@endsection

@push('styles')
<style>
    .ledger-toolbar{grid-template-columns:minmax(260px,1fr)170px 170px 130px}.ledger-layout{grid-template-columns:minmax(0,1fr)320px}.money-cell{text-align:right;font-weight:800;white-space:nowrap}.green-text{color:#067647!important}.red-text{color:#dc2626!important}.orange-text{color:#b54708!important}.debit-text{color:#067647}.credit-text{color:#dc2626}.opening-row td{background:#fff7ed}.total-row td{background:#f8fafc}.closing-row td{background:#eef4ff;color:#1d4ed8}.info-list{display:grid;gap:12px}.info-row{display:flex;align-items:flex-start;justify-content:space-between;gap:12px;font-size:14px}.info-row span:first-child{color:var(--muted)}.info-row strong{text-align:right}@media(max-width:1320px){.ledger-layout{grid-template-columns:1fr}.ledger-toolbar{grid-template-columns:1fr 1fr 1fr}.right-stack{grid-template-columns:1fr 1fr}}@media(max-width:880px){.ledger-toolbar,.right-stack{grid-template-columns:1fr}.ledger-layout{grid-template-columns:1fr}}
</style>
@endpush
