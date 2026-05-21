@extends('layouts.app')

@section('title', 'Transaction List')

@section('content')
@php
    $money = fn ($amount) => ($currency ?? 'BDT') . ' ' . number_format((float) $amount, 2);
    $natureBadge = function ($nature) {
        return match ($nature) {
            'Receipt' => 'badge-success',
            'Payment' => 'badge-danger',
            'Due' => 'badge-warning',
            'Advance' => 'badge-purple',
            default => 'badge-neutral',
        };
    };
    $statusBadge = function ($status) {
        $statusText = strtolower((string) $status);
        if (str_contains($statusText, 'posted')) {
            return 'badge-success';
        }
        if (str_contains($statusText, 'draft') || str_contains($statusText, 'pending')) {
            return 'badge-warning';
        }
        if (str_contains($statusText, 'reverse') || str_contains($statusText, 'cancel')) {
            return 'badge-danger';
        }
        return 'badge-neutral';
    };
@endphp

<div class="page-title">
    <div>
        <h2>Transaction List</h2>
        <p>View, search, export, and inspect all vouchers posted through the accounting engine.</p>
    </div>
    <div class="quick-actions" style="display:flex;gap:10px;flex-wrap:wrap">
        <a class="button btn-outline" href="{{ route('accounting-reports.transactions.export', request()->query()) }}">Export CSV</a>
        @if(Route::has('transactions.create'))
            <a class="button btn-primary" href="{{ route('transactions.create') }}">+ Add Transaction</a>
        @endif
    </div>
</div>

@if(session('success'))
    <div class="card" style="padding:14px 18px;margin-bottom:18px;border-color:#bbf7d0;background:#f0fdf4;color:#166534;font-weight:750">
        {{ session('success') }}
    </div>
@endif

@if($errors->any())
    <div class="card" style="padding:14px 18px;margin-bottom:18px;border-color:#fecaca;background:#fef2f2;color:#991b1b;font-weight:750">
        {{ $errors->first() }}
    </div>
@endif

<div class="stats-grid" style="margin-bottom:18px">
    <div class="card stat-card"><small>Total Transactions</small><strong>{{ number_format((int) ($stats->total_transactions ?? 0)) }}</strong></div>
    <div class="card stat-card"><small>Total Receipt</small><strong style="color:#067647">{{ $money($stats->total_receipt ?? 0) }}</strong></div>
    <div class="card stat-card"><small>Total Payment</small><strong style="color:#dc2626">{{ $money($stats->total_payment ?? 0) }}</strong></div>
    <div class="card stat-card"><small>Draft / Pending</small><strong style="color:#b54708">{{ number_format((int) ($stats->total_draft ?? 0)) }}</strong></div>
</div>

<form method="GET" action="{{ route('accounting-reports.transactions.index') }}" class="card toolbar five" style="margin-bottom:18px">
    <div class="field search-field">
        <label>Search</label>
        <span>⌕</span>
        <input name="q" value="{{ $filters['q'] ?? '' }}" placeholder="Voucher, head, party, reference...">
    </div>
    <div>
        <label>From Date</label>
        <input type="date" name="from_date" value="{{ $filters['from_date'] ?? '' }}">
    </div>
    <div>
        <label>To Date</label>
        <input type="date" name="to_date" value="{{ $filters['to_date'] ?? '' }}">
    </div>
    <div>
        <label>Nature</label>
        <select name="nature">
            @foreach(['All', 'Payment', 'Receipt', 'Due', 'Advance', 'Adjustment'] as $nature)
                <option value="{{ $nature }}" @selected(($filters['nature'] ?? 'All') === $nature)>{{ $nature }}</option>
            @endforeach
        </select>
    </div>
    <div>
        <label>Status</label>
        <select name="status">
            @foreach(['All', 'Posted', 'Draft', 'Pending Review', 'Reversed', 'Cancelled'] as $status)
                <option value="{{ $status }}" @selected(($filters['status'] ?? 'All') === $status)>{{ $status }}</option>
            @endforeach
        </select>
    </div>
    <div style="display:flex;gap:10px;align-items:end">
        <button class="btn-primary" type="submit">Filter</button>
        <a class="button btn-ghost" href="{{ route('accounting-reports.transactions.index') }}">Reset</a>
    </div>
</form>

<div class="card table-card">
    <div class="card-head">
        <div>
            <h3>Transactions</h3>
            <p>Showing saved vouchers from voucher headers with their generated debit/credit details.</p>
        </div>
        <span class="badge badge-primary">{{ $transactions->total() }} record(s)</span>
    </div>

    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Voucher No.</th>
                    <th>Transaction Head</th>
                    <th>Party / Person</th>
                    <th>Settlement</th>
                    <th>Nature</th>
                    <th style="text-align:right">Amount</th>
                    <th>Status</th>
                    <th>Reference</th>
                    <th style="text-align:right">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse($transactions as $transaction)
                    <tr>
                        <td>{{ optional(
                            \Illuminate\Support\Carbon::parse($transaction->voucher_date)
                        )->format('d M Y') }}</td>
                        <td class="code">{{ $transaction->voucher_no }}</td>
                        <td class="strong">{{ $transaction->purpose_name ?: $transaction->purpose_code ?: $transaction->voucher_type_code }}</td>
                        <td>{{ $transaction->party_name ?: '—' }}</td>
                        <td>{{ $transaction->settlement ?: '—' }}</td>
                        <td><span class="badge {{ $natureBadge($transaction->nature) }}">{{ $transaction->nature }}</span></td>
                        <td style="text-align:right;font-weight:850">{{ $money($transaction->amount) }}</td>
                        <td><span class="badge {{ $statusBadge($transaction->status) }}">{{ $transaction->status }}</span></td>
                        <td>{{ $transaction->reference_no ?: '—' }}</td>
                        <td style="text-align:right">
                            <a class="button btn-ghost" style="min-height:34px;padding:7px 11px" href="{{ route('accounting-reports.transactions.show', $transaction->voucher_id) }}">View</a>
                        </td>
                    </tr>
                @empty
                    <tr data-empty="true">
                        <td colspan="10" class="empty-state">No transaction found for the selected filter.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="table-footer">
        <span>Showing {{ $transactions->firstItem() ?? 0 }} to {{ $transactions->lastItem() ?? 0 }} of {{ $transactions->total() }} entries</span>
        <div>{{ $transactions->links() }}</div>
    </div>
</div>
@endsection
