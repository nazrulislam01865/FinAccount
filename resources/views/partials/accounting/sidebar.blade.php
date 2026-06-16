@php
    $masterMenuActive = request()->routeIs('master.*');
@endphp

<aside class="hg-side">
    <div class="hg-brand">
        <div class="hg-logo">HG</div>
        <div class="hg-brand-copy">
            <h2>HisebGhor</h2>
            <p>Sales, Payment & Liability MVP</p>
        </div>
    </div>

    <nav class="hg-nav">
        <a href="{{ route('dashboard') }}" class="{{ request()->routeIs('dashboard') ? 'active' : '' }}">
            <span class="hg-nav-icon">🏠</span><span class="hg-nav-text">Dashboard</span>
        </a>
        <a href="{{ route('transactions.create') }}" class="{{ request()->routeIs('transactions.create', 'transactions.edit') ? 'active' : '' }}">
            <span class="hg-nav-icon">🧾</span><span class="hg-nav-text">Transaction Entry</span>
        </a>
        <a href="{{ route('transactions.index') }}" class="{{ request()->routeIs('transactions.index') ? 'active' : '' }}">
            <span class="hg-nav-icon">📚</span><span class="hg-nav-text">Transaction Register</span>
        </a>
        <a href="{{ route('chart-of-accounts.index') }}" class="{{ request()->routeIs('chart-of-accounts.*') ? 'active' : '' }}">
            <span class="hg-nav-icon">📘</span><span class="hg-nav-text">Chart of Accounts</span>
        </a>
        <a href="{{ route('money-accounts.index') }}" class="{{ request()->routeIs('money-accounts.*') ? 'active' : '' }}">
            <span class="hg-nav-icon">🏦</span><span class="hg-nav-text">Money Accounts</span>
        </a>
        <a href="{{ route('parties.index') }}" class="{{ request()->routeIs('parties.*') ? 'active' : '' }}">
            <span class="hg-nav-icon">👥</span><span class="hg-nav-text">Parties</span>
        </a>

        <details class="hg-nav-group" @if($masterMenuActive) open @endif>
            <summary class="{{ $masterMenuActive ? 'active' : '' }}">
                <span class="hg-nav-icon">🗂️</span>
                <span class="hg-nav-text">Master</span>
                <span class="hg-nav-caret" aria-hidden="true">›</span>
            </summary>

            <div class="hg-nav-submenu">
                <span class="hg-nav-subheading">Business Masters</span>
                <a
                    href="{{ route('master.index', 'party-types') }}"
                    class="{{ request()->routeIs('master.index') && request()->route('section') === 'party-types' ? 'active' : '' }}"
                >
                    <span class="hg-nav-icon">👥</span><span class="hg-nav-text">Party Types</span>
                </a>
                <a
                    href="{{ route('master.index', 'money-account-types') }}"
                    class="{{ request()->routeIs('master.index') && request()->route('section') === 'money-account-types' ? 'active' : '' }}"
                >
                    <span class="hg-nav-icon">🏦</span><span class="hg-nav-text">Money Account Types</span>
                </a>

                <span class="hg-nav-subheading">Transaction Setup</span>
                <a
                    href="{{ route('master.index', 'transaction-categories') }}"
                    class="{{ request()->routeIs('master.index') && request()->route('section') === 'transaction-categories' ? 'active' : '' }}"
                >
                    <span class="hg-nav-icon">🏷️</span><span class="hg-nav-text">Transaction Categories</span>
                </a>
                <a
                    href="{{ route('master.voucher-sequences.index') }}"
                    class="{{ request()->routeIs('master.voucher-sequences.*') ? 'active' : '' }}"
                >
                    <span class="hg-nav-icon">🔢</span><span class="hg-nav-text">Voucher Numbering</span>
                </a>
            </div>
        </details>

        <a href="{{ route('accounting-rules.index') }}" class="{{ request()->routeIs('accounting-rules.*') ? 'active' : '' }}">
            <span class="hg-nav-icon">⚙️</span><span class="hg-nav-text">Accounting Rules</span>
        </a>
        <a href="{{ route('transaction-heads.index') }}" class="{{ request()->routeIs('transaction-heads.*') ? 'active' : '' }}">
            <span class="hg-nav-icon">🏷️</span><span class="hg-nav-text">Transaction Heads</span>
        </a>
        <a href="{{ route('journal-entries.index') }}" class="{{ request()->routeIs('journal-entries.*') ? 'active' : '' }}">
            <span class="hg-nav-icon">📒</span><span class="hg-nav-text">Journal Entries</span>
        </a>
        <a href="{{ route('balances.index') }}" class="{{ request()->routeIs('balances.*') ? 'active' : '' }}">
            <span class="hg-nav-icon">📊</span><span class="hg-nav-text">Balances</span>
        </a>
        <a href="{{ route('basic-statements.index') }}" class="{{ request()->routeIs('basic-statements.*') ? 'active' : '' }}">
            <span class="hg-nav-icon">📈</span><span class="hg-nav-text">Basic Statements</span>
        </a>
    </nav>
</aside>
