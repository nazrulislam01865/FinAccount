@extends('layouts.app')

@section('title', 'Accounting Reports')

@section('content')
@php
    $cards = [
        [
            'title' => 'Transaction List',
            'description' => 'Search, review, export, and reverse posted vouchers with debit/credit preview.',
            'route' => 'accounting-reports.transactions.index',
            'icon' => '📄',
            'permission' => 'transactions.view',
        ],
        [
            'title' => 'Cash / Bank Book',
            'description' => 'Cash and bank inflow, outflow, and running balance from voucher detail lines.',
            'route' => 'accounting-reports.cash-bank-book.index',
            'icon' => '🏦',
            'permission' => 'cash-bank-book.view',
        ],
        [
            'title' => 'Trial Balance',
            'description' => 'Opening, period, and closing debit/credit balances by ledger account.',
            'route' => 'accounting-reports.trial-balance.index',
            'icon' => 'TB',
            'permission' => 'reports.view',
        ],
        [
            'title' => 'Income Statement',
            'description' => 'Revenue, cost, expenses, gross profit, and net profit for the selected period.',
            'route' => 'accounting-reports.income-statement.index',
            'icon' => 'IS',
            'permission' => 'reports.view',
        ],
    ];

    $user = auth()->user();
@endphp

<div class="page-title">
    <div>
        <h2>Accounting Reports</h2>
        <p>Reusable report pages generated from posted voucher detail debit/credit rows.</p>
    </div>
</div>

<div class="grid two">
    @foreach($cards as $card)
        @if(\Illuminate\Support\Facades\Route::has($card['route']) && ($user?->canViewRoute($card['route']) || $user?->hasPermission($card['permission'])))
            <a href="{{ route($card['route']) }}" class="card info-card" style="display:block;min-height:170px">
                <div style="display:flex;align-items:center;gap:14px;margin-bottom:16px">
                    <div class="nav-icon" style="width:42px;height:42px;background:var(--primary-soft);color:var(--primary)">{{ $card['icon'] }}</div>
                    <h3 style="margin:0;font-size:18px">{{ $card['title'] }}</h3>
                </div>
                <p>{{ $card['description'] }}</p>
                <span class="button btn-outline" style="margin-top:18px">Open Report</span>
            </a>
        @endif
    @endforeach
</div>
@endsection
