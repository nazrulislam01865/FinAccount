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
            'permission' => 'reports.full',
        ],
        [
            'title' => 'Income Statement',
            'description' => 'Revenue, cost, expenses, gross profit, and net profit for the selected period.',
            'route' => 'accounting-reports.income-statement.index',
            'icon' => 'IS',
            'permission' => 'reports.full',
        ],
        [
            'title' => 'Balance Sheet',
            'description' => 'Assets, liabilities, equity, retained profit, and balance check as of a date.',
            'route' => 'accounting-reports.balance-sheet.index',
            'icon' => 'BS',
            'permission' => 'reports.full',
        ],
        [
            'title' => 'Cash Flow Statement',
            'description' => 'Operating, investing, and financing cash movement from cash/bank voucher lines.',
            'route' => 'accounting-reports.cash-flow-statement.index',
            'icon' => 'CF',
            'permission' => 'reports.full',
        ],
        [
            'title' => 'Customer Receivable',
            'description' => 'Customer-wise opening, debit, credit, and closing receivable movement.',
            'route' => 'accounting-reports.customer-receivables.index',
            'icon' => 'CR',
            'permission' => 'customer-ledgers.view',
        ],
        [
            'title' => 'Supplier Payable',
            'description' => 'Supplier-wise opening, debit, credit, and closing payable movement.',
            'route' => 'accounting-reports.supplier-payables.index',
            'icon' => 'SP',
            'permission' => 'supplier-ledgers.view',
        ],
        [
            'title' => 'Sales Report',
            'description' => 'Sales/income ledger movements by voucher, party, transaction head, and account.',
            'route' => 'accounting-reports.sales-report.index',
            'icon' => 'SR',
            'permission' => 'reports.full',
        ],
        [
            'title' => 'Expense Report',
            'description' => 'Expense ledger movements by voucher, party, transaction head, and account.',
            'route' => 'accounting-reports.expense-report.index',
            'icon' => 'ER',
            'permission' => 'reports.full',
        ],
    ];

    $user = auth()->user();
@endphp

<div class="page-title">
    <div>
        <h2>Accounting Reports</h2>
        <p>Phase 6 reports are standardized on posted voucher detail debit/credit rows, not voucher header amount.</p>
    </div>
</div>

<div class="grid two">
    @foreach($cards as $card)
        @if(\Illuminate\Support\Facades\Route::has($card['route']) && $user?->canViewRoute($card['route']))
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
