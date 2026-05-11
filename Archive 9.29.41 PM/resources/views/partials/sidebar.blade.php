@php
    $routeName = request()->route()?->getName();
    $isActive = fn ($name) => $routeName === $name ? 'active' : '';
    $isPrefix = fn ($prefix) => str_starts_with((string) $routeName, $prefix) ? 'active' : '';
@endphp
<aside class="sidebar">
    <div class="brand">
        <div class="brand-mark">▥</div>
        <div>
            <h1>FinAcco</h1>
            <p>Accounting System</p>
        </div>
    </div>

    <div class="nav-title">Setup</div>
    <a href="{{ route('setup.company') }}" class="nav-item {{ $isActive('setup.company') }}"><div class="nav-icon">1</div><span>Company Setup</span></a>
    <a href="{{ route('setup.chart-of-accounts') }}" class="nav-item {{ $isActive('setup.chart-of-accounts') }}"><div class="nav-icon">2</div><span>Chart of Accounts</span></a>
    <a href="{{ route('setup.cash-bank-accounts') }}" class="nav-item {{ $isActive('setup.cash-bank-accounts') }}"><div class="nav-icon">3</div><span>Cash / Bank Setup</span></a>
    <a href="{{ route('setup.parties') }}" class="nav-item {{ $isActive('setup.parties') }}"><div class="nav-icon">4</div><span>Party / Person Setup</span></a>
    <a href="{{ route('setup.transaction-heads') }}" class="nav-item {{ $isActive('setup.transaction-heads') }}"><div class="nav-icon">5</div><span>Transaction Head Setup</span></a>

    <div class="nav-title">Settings</div>
    <a href="{{ route('settings.users-roles') }}" class="nav-item {{ $isActive('settings.users-roles') }}"><div class="nav-icon">U</div><span>Users & Roles</span></a>

    <div class="help-card">
        <div class="help-badge">?</div>
        <div>
            <strong>Need Help?</strong>
            <span>View setup guide ↗</span>
        </div>
    </div>
</aside>
