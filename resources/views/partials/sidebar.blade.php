@php
    use Illuminate\Support\Facades\Route;

    $routeName = request()->route()?->getName();
    $isActive = fn ($name) => $routeName === $name ? 'active' : '';
@endphp

<aside class="sidebar">
    <div class="brand">
        <div class="brand-mark">▥</div>
        <div>
            <h1>FinAcco</h1>
            <p>Accounting System</p>
        </div>
    </div>

    <div class="nav-title">Main Menu</div>

    <a
        href="{{ Route::has('transactions.create') ? route('transactions.create') : '#' }}"
        class="nav-item {{ $isActive('transactions.create') }}"
    >
        <div class="nav-icon">＋</div>
        <span>Add Transaction</span>
    </a>

    <a href="#" class="nav-item">
        <div class="nav-icon">📄</div>
        <span>Transaction List</span>
    </a>

    <a href="#" class="nav-item">
        <div class="nav-icon">⏳</div>
        <span>Due Management</span>
    </a>

    <a href="#" class="nav-item">
        <div class="nav-icon">↗</div>
        <span>Advance Entry</span>
    </a>

    <a href="#" class="nav-item">
        <div class="nav-icon">📘</div>
        <span>Ledger Report</span>
    </a>

    <a href="#" class="nav-item">
        <div class="nav-icon">🏦</div>
        <span>Cash / Bank Book</span>
    </a>

    <div class="nav-title">Setup</div>

    <a href="{{ route('setup.company') }}" class="nav-item {{ $isActive('setup.company') }}">
        <div class="nav-icon">1</div>
        <span>Company Setup</span>
    </a>

    <a href="{{ route('setup.chart-of-accounts') }}" class="nav-item {{ $isActive('setup.chart-of-accounts') }}">
        <div class="nav-icon">2</div>
        <span>Chart of Accounts</span>
    </a>

    <a href="{{ route('setup.cash-bank-accounts') }}" class="nav-item {{ $isActive('setup.cash-bank-accounts') }}">
        <div class="nav-icon">3</div>
        <span>Cash / Bank Setup</span>
    </a>

    <a href="{{ route('setup.parties') }}" class="nav-item {{ $isActive('setup.parties') }}">
        <div class="nav-icon">4</div>
        <span>Party / Person Setup</span>
    </a>

    <a href="{{ route('setup.transaction-heads') }}" class="nav-item {{ $isActive('setup.transaction-heads') }}">
        <div class="nav-icon">5</div>
        <span>Transaction Head Setup</span>
    </a>

    <a href="{{ route('setup.ledger-mapping') }}" class="nav-item {{ $isActive('setup.ledger-mapping') }}">
        <div class="nav-icon">6</div>
        <span>Ledger Mapping</span>
    </a>

    <a
        href="{{ Route::has('setup.opening-balances') ? route('setup.opening-balances') : '#' }}"
        class="nav-item {{ $isActive('setup.opening-balances') }}"
    >
        <div class="nav-icon">7</div>
        <span>Opening Balance Setup</span>
    </a>

    <a
        href="{{ Route::has('setup.voucher-numbering') ? route('setup.voucher-numbering') : '#' }}"
        class="nav-item {{ $isActive('setup.voucher-numbering') }}"
    >
        <div class="nav-icon">8</div>
        <span>Voucher Numbering</span>
    </a>

    <div class="nav-title">Settings</div>

    <a href="{{ route('settings.users-roles') }}" class="nav-item {{ $isActive('settings.users-roles') }}">
        <div class="nav-icon">U</div>
        <span>Users & Roles</span>
    </a>

    <div class="help-card">
        <div class="help-badge">?</div>
        <div>
            <strong>Need Help?</strong>
            <span>Daily entry guide ↗</span>
        </div>
    </div>
</aside>
