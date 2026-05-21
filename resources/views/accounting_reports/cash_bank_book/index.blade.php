@extends('layouts.app')

@section('title', 'Cash / Bank Book')

@section('content')
@php
    $money = fn ($amount) => ($currency ?? 'BDT') . ' ' . number_format((float) $amount, 2);
    $selectedAccount = $filters['account_id'] ?? '';
@endphp

<div class="page-title">
    <div>
        <h2>Cash / Bank Book</h2>
        <p>Cash and bank movement generated from posted voucher debit/credit lines.</p>
    </div>
    <div class="quick-actions" style="display:flex;gap:10px;flex-wrap:wrap">
        <a class="button btn-outline" href="{{ route('accounting-reports.cash-bank-book.export', request()->query()) }}">Export CSV</a>
        <button class="btn-ghost" type="button" onclick="window.print()">Print</button>
    </div>
</div>

<form method="GET" action="{{ route('accounting-reports.cash-bank-book.index') }}" class="card toolbar five" style="margin-bottom:18px">
    <div>
        <label>Cash / Bank Account</label>
        <select name="account_id">
            <option value="">All Cash & Bank</option>
            @foreach($report['cash_bank_accounts'] as $account)
                <option value="{{ $account->account_id }}" @selected((string) $selectedAccount === (string) $account->account_id)>
                    {{ trim(($account->account_code ? $account->account_code . ' - ' : '') . $account->account_name) }}
                </option>
            @endforeach
        </select>
    </div>
    <div>
        <label>Book Type</label>
        <select name="book_type">
            @foreach(['All' => 'Combined Book', 'Cash Book Only' => 'Cash Book Only', 'Bank Book Only' => 'Bank Book Only'] as $value => $label)
                <option value="{{ $value }}" @selected(($filters['book_type'] ?? 'All') === $value)>{{ $label }}</option>
            @endforeach
        </select>
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
        <label>Transaction Type</label>
        <select name="transaction_type">
            @foreach(['All', 'Inflow', 'Outflow'] as $type)
                <option value="{{ $type }}" @selected(($filters['transaction_type'] ?? 'All') === $type)>{{ $type }}</option>
            @endforeach
        </select>
    </div>
    <div style="display:flex;gap:10px;align-items:end">
        <button class="btn-primary" type="submit">Run</button>
        <a class="button btn-ghost" href="{{ route('accounting-reports.cash-bank-book.index') }}">Reset</a>
    </div>
</form>

<div class="stats-grid" style="margin-bottom:18px">
    <div class="card stat-card"><small>Opening Balance</small><strong style="color:#b54708">{{ $money($report['opening_balance']) }}</strong></div>
    <div class="card stat-card"><small>Total Inflow</small><strong style="color:#067647">{{ $money($report['total_inflow']) }}</strong></div>
    <div class="card stat-card"><small>Total Outflow</small><strong style="color:#dc2626">{{ $money($report['total_outflow']) }}</strong></div>
    <div class="card stat-card"><small>Closing Balance</small><strong>{{ $money($report['closing_balance']) }}</strong></div>
</div>

<div class="layout">
    <div class="left-stack">
        <div class="card table-card">
            <div class="card-head">
                <div>
                    <h3>Cash / Bank Book Statement</h3>
                    <p>Debit to cash/bank is inflow. Credit from cash/bank is outflow.</p>
                </div>
                <span class="badge badge-primary">{{ $report['total_entries'] }} entries</span>
            </div>
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Voucher</th>
                            <th>Account</th>
                            <th>Particulars</th>
                            <th>Reference</th>
                            <th style="text-align:right">Inflow</th>
                            <th style="text-align:right">Outflow</th>
                            <th style="text-align:right">Running Balance</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($report['rows'] as $row)
                            <tr>
                                <td>{{ \Illuminate\Support\Carbon::parse($row->journal_date)->format('d M Y') }}</td>
                                <td class="code">{{ $row->voucher_no ?: $row->journal_no }}</td>
                                <td class="strong">{{ trim(($row->account_code ? $row->account_code . ' - ' : '') . $row->account_name) }}</td>
                                <td>{{ $row->line_description ?: $row->voucher_description ?: '—' }}</td>
                                <td>{{ $row->reference_no ?: '—' }}</td>
                                <td style="text-align:right;font-weight:850;color:#067647">{{ (float) $row->debit > 0 ? $money($row->debit) : '—' }}</td>
                                <td style="text-align:right;font-weight:850;color:#dc2626">{{ (float) $row->credit > 0 ? $money($row->credit) : '—' }}</td>
                                <td style="text-align:right;font-weight:900">{{ $money($row->running_balance) }}</td>
                            </tr>
                        @empty
                            <tr data-empty="true">
                                <td colspan="8" class="empty-state">No cash/bank movement found for the selected filter.</td>
                            </tr>
                        @endforelse
                        <tr>
                            <td colspan="5" class="strong">Period Total</td>
                            <td style="text-align:right;font-weight:900;color:#067647">{{ $money($report['total_inflow']) }}</td>
                            <td style="text-align:right;font-weight:900;color:#dc2626">{{ $money($report['total_outflow']) }}</td>
                            <td></td>
                        </tr>
                        <tr>
                            <td colspan="7" class="strong">Closing Balance</td>
                            <td style="text-align:right;font-weight:900">{{ $money($report['closing_balance']) }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <aside class="right-stack">
        <div class="card info-card">
            <h3>Account Balances</h3>
            <div class="form-grid" style="gap:10px;margin-top:12px">
                @forelse($report['account_balances'] as $balance)
                    <div style="display:flex;justify-content:space-between;gap:12px;border:1px solid var(--line);border-radius:12px;padding:12px;background:#fff">
                        <div>
                            <strong style="display:block;font-size:13px">{{ $balance->account_name }}</strong>
                            <small class="muted">{{ $balance->account_code }}</small>
                        </div>
                        <b style="white-space:nowrap">{{ $money($balance->balance) }}</b>
                    </div>
                @empty
                    <p class="muted">No cash/bank account balance found.</p>
                @endforelse
            </div>
        </div>
        <div class="card helper-card">
            <h3>Report Rule</h3>
            <p>This screen reads only posted voucher detail rows linked with cash/bank ledger accounts. It does not calculate balances from voucher amount alone.</p>
        </div>
    </aside>
</div>
@endsection
