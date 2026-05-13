@extends('layouts.app')
@section('title', 'Dashboard | Accounting System')
@section('content')
<div class="page-title">
    <div>
        <span class="page-label">Dashboard</span>
        <h2>Setup Dashboard</h2>
        <p>Complete master setup and foundation modules before starting accounting transactions.</p>
    </div>
</div>

@include('partials.setup-progress', ['current' => 1])

<div class="stats-grid">
    <div class="card stat-card"><small>Setup Modules</small><strong>5</strong></div>
    <div class="card stat-card"><small>Master Data</small><strong>6</strong></div>
    <div class="card stat-card"><small>Dynamic Dropdowns</small><strong>8</strong></div>
    <div class="card stat-card"><small>Sprint Status</small><strong>1</strong></div>
</div>

<div class="layout" style="margin-top:22px">
    <div class="left-stack">
        <div class="card helper-card">
            <h3>Recommended Frontend Completion Order</h3>
            <p>Build and check the frontend first, then connect backend APIs one by one.</p>
            <div class="step-list">
                <div class="step-row"><div class="nav-icon done-dot">1</div><div><strong>Master setup pages</strong><small>Business types, currencies, time zones, account types, party types, banks.</small></div></div>
                <div class="step-row"><div class="nav-icon done-dot">2</div><div><strong>Company setup</strong><small>Uses business type, currency, and time zone APIs.</small></div></div>
                <div class="step-row"><div class="nav-icon done-dot">3</div><div><strong>Chart of accounts</strong><small>Uses dynamic account type and parent account APIs.</small></div></div>
                <div class="step-row"><div class="nav-icon done-dot">4</div><div><strong>Cash / bank setup</strong><small>Uses banks and cash/bank ledger accounts.</small></div></div>
                <div class="step-row"><div class="nav-icon done-dot">5</div><div><strong>Party and transaction head setup</strong><small>Uses party types, linked ledgers, and settlement types.</small></div></div>
            </div>
        </div>
    </div>
    <aside class="right-stack">
<div class="card info-card">
            <h3>Frontend Mode</h3>
            <p>These pages can render before the backend is ready. Dropdowns will show backend data when the API endpoints are created.</p>
        </div>
    </aside>
</div>
@endsection
