@extends('layouts.app')

@section('title', 'Accounting Reports')

@section('content')
<div class="page-title">
    <div>
        <h2>Accounting Reports</h2>
        <p>Select a report from the Reports submenu in the sidebar.</p>
    </div>
</div>

<div class="card info-card">
    <h3 style="margin:0 0 8px;font-size:18px">Reports are now opened from the sidebar submenu</h3>
    <p style="margin:0;color:var(--muted)">Use Reports in the left menu, then choose Transaction List, Cash / Bank Book, Ledger Report, Trial Balance, Income Statement, Balance Sheet, Cash Flow Statement, Customer Receivables, Supplier Payables, Sales Report, Expense Report, or Audit Log Report.</p>
</div>
@endsection
