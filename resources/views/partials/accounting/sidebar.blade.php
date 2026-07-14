@php
    $user = auth()->user();
    $brand = \App\Support\HisebGhorBrand::data();
    $balanceSection = request()->routeIs('balances.*') ? request()->query('section', 'accounts') : null;
    $statementSection = request()->routeIs('basic-statements.*') ? request()->query('section', 'income') : null;
    $isReportRoute = request()->routeIs('reports.*');
    $canAnyConfiguration = $user?->canAnyAccounting([
        'company_setup.view', 'company_setup.manage',
        'business_types.view', 'business_types.manage', 'currencies.view', 'currencies.manage',
        'time_zones.view', 'time_zones.manage', 'financial_years.view', 'financial_years.manage',
        'chart_of_accounts.view', 'chart_of_accounts.manage', 'opening_balances.view', 'opening_balances.manage', 'accounting_rules.view', 'accounting_rules.manage',
        'transaction_heads.view', 'transaction_heads.manage', 'transaction_categories.view', 'transaction_categories.manage',
        'voucher_numbering.view', 'voucher_numbering.manage', 'party_types.view', 'party_types.manage',
        'parties.view', 'parties.manage', 'money_account_types.view', 'money_account_types.manage',
        'money_accounts.view', 'money_accounts.manage', 'master_data.view',
    ]);
    $canOpenOtherMasterData = $user?->canAnyAccounting([
        'master_data.view', 'business_types.view', 'business_types.manage',
        'currencies.view', 'currencies.manage', 'time_zones.view', 'time_zones.manage',
        'financial_years.view', 'financial_years.manage', 'party_types.view', 'party_types.manage',
        'money_account_types.view', 'money_account_types.manage', 'transaction_categories.view',
        'transaction_categories.manage', 'voucher_numbering.view', 'voucher_numbering.manage',
    ]);
    $isOtherMasterDataRoute = request()->routeIs(
        'master.overview',
        'master.index',
        'master.voucher-sequences.*',
        'master.business-types.*',
        'master.currencies.*',
        'master.time-zones.*',
        'master.financial-years.*',
    );
    $canAnySystem = $user?->canAnyAccounting(['users.view', 'users.manage', 'role_matrix.view', 'role_matrix.manage', 'settings.manage']);
    $homeDestination = \App\Support\AccountingRbac::firstAllowedDestination($user);
@endphp

<aside class="hg-side" id="hgAccountingSidebar" aria-label="Main navigation">
    <div class="hg-side-header">
    <a class="hg-brand" href="{{ route($homeDestination['route'], $homeDestination['parameters']) }}">
        @if($brand['logo_url'])
            <span class="hg-logo hg-logo-image"><img src="{{ $brand['logo_url'] }}" alt="{{ $brand['name'] }} logo"></span>
        @else
            <span class="hg-logo">HG</span>
        @endif
        <span class="hg-brand-copy"><h2>{{ $brand['name'] }}</h2><p>Simple Accounting MVP</p></span>
    </a>
    <button type="button" class="hg-sidebar-close" data-hg-sidebar-close aria-label="Close navigation menu">×</button>
    </div>

    <nav class="hg-nav" aria-label="Accounting navigation">
        @if($user?->canAccounting('dashboard.view'))
        <a href="{{ route('dashboard') }}" class="{{ request()->routeIs('dashboard') ? 'active' : '' }}"><span class="hg-nav-icon">🏠</span><span class="hg-nav-text">Dashboard</span></a>
        @endif

        @if($user?->canAnyAccounting(['transactions.view','transactions.manage','journals.view']))
        <div class="hg-nav-section">Transactions</div>
        @if($user?->canAccounting('transactions.manage'))
        <a href="{{ route('transactions.create') }}" class="{{ request()->routeIs('transactions.create', 'transactions.edit') ? 'active' : '' }}"><span class="hg-nav-icon">🧾</span><span class="hg-nav-text">Transaction Entry</span></a>
        @endif
        @if($user?->canAccounting('transactions.view'))
        <a href="{{ route('transactions.index') }}" class="{{ request()->routeIs('transactions.index') ? 'active' : '' }}"><span class="hg-nav-icon">📚</span><span class="hg-nav-text">Transaction Register</span></a>
        @endif
        @if($user?->canAccounting('journals.view'))
        <a href="{{ route('journal-entries.index') }}" class="{{ request()->routeIs('journal-entries.*') ? 'active' : '' }}"><span class="hg-nav-icon">📒</span><span class="hg-nav-text">Journal Entries</span></a>
        @endif
        @endif

        @if($user?->canAnyAccounting(['transactions.view','transactions.manage']))
        <div class="hg-nav-section">Feed Business</div>
        @if($user?->canAccounting('transactions.manage'))
        <a href="{{ route('feed.business-tracking.index') }}" class="{{ request()->routeIs('feed.business-tracking.*') ? 'active' : '' }}"><span class="hg-nav-icon">◫</span><span class="hg-nav-text">Business Tracking</span></a>
        <a href="{{ route('feed.purchases.create') }}" class="{{ request()->routeIs('feed.purchases.*') ? 'active' : '' }}"><span class="hg-nav-icon">🛒</span><span class="hg-nav-text">Feed Purchase</span></a>
        <a href="{{ route('feed.sales.create') }}" class="{{ request()->routeIs('feed.sales.*') ? 'active' : '' }}"><span class="hg-nav-icon">🧾</span><span class="hg-nav-text">Feed Sale</span></a>
        @endif
        @if($user?->canAccounting('transactions.view'))
        <a href="{{ route('feed.inventory.index') }}" class="{{ request()->routeIs('feed.inventory.*') ? 'active' : '' }}"><span class="hg-nav-icon">▦</span><span class="hg-nav-text">Feed Inventory</span></a>
        @endif
        @if($user?->canAccounting('transactions.manage'))
        <a href="{{ route('feed.setup.index') }}" class="{{ request()->routeIs('feed.setup.*') ? 'active' : '' }}"><span class="hg-nav-icon">⚙️</span><span class="hg-nav-text">Feed Setup</span></a>
        @endif
        @endif

        @if($user?->canAnyAccounting(['balances.view','statements.view']))
        <div class="hg-nav-section">Reports</div>
        @if($user?->canAccounting('statements.view'))
        <a href="{{ route('reports.income-statement') }}" class="{{ request()->routeIs('reports.income-statement') ? 'active' : '' }}"><span class="hg-nav-icon">📈</span><span class="hg-nav-text">Income Statement</span></a>
        <a href="{{ route('reports.balance-sheet') }}" class="{{ request()->routeIs('reports.balance-sheet') ? 'active' : '' }}"><span class="hg-nav-icon">⚖️</span><span class="hg-nav-text">Balance Sheet</span></a>
        <a href="{{ route('reports.trial-balance') }}" class="{{ request()->routeIs('reports.trial-balance') ? 'active' : '' }}"><span class="hg-nav-icon">🧮</span><span class="hg-nav-text">Trial Balance</span></a>
        <a href="{{ route('basic-statements.index', ['section' => 'cash-flow']) }}#cash-flow-statement" class="{{ request()->routeIs('basic-statements.*') && $statementSection === 'cash-flow' ? 'active' : '' }}"><span class="hg-nav-icon">💸</span><span class="hg-nav-text">Cash Flow Statement</span></a>
        @endif
        @if($user?->canAccounting('balances.view'))
        <a href="{{ route('reports.ledger-report') }}" class="{{ request()->routeIs('reports.ledger-report') ? 'active' : '' }}"><span class="hg-nav-icon">📖</span><span class="hg-nav-text">Ledger Report</span></a>
        <a href="{{ route('reports.due-report') }}" class="{{ request()->routeIs('reports.due-report') ? 'active' : '' }}"><span class="hg-nav-icon">📌</span><span class="hg-nav-text">Due Report</span></a>
        @if($user?->canAccounting('transactions.manage'))
        <a href="{{ route('reports.due-management') }}" class="{{ request()->routeIs('reports.due-management*') ? 'active' : '' }}"><span class="hg-nav-icon">✅</span><span class="hg-nav-text">Due Management</span></a>
        @endif
        <a href="{{ route('balances.index', ['section' => 'accounts']) }}#account-balances" class="{{ ! $isReportRoute && $balanceSection === 'accounts' ? 'active' : '' }}"><span class="hg-nav-icon">💰</span><span class="hg-nav-text">Account Balances</span></a>
        <a href="{{ route('balances.index', ['section' => 'parties']) }}#party-balances" class="{{ ! $isReportRoute && $balanceSection === 'parties' ? 'active' : '' }}"><span class="hg-nav-icon">👥</span><span class="hg-nav-text">Party Balances</span></a>
        @endif
        @endif

        @if($canAnyConfiguration)
        <div class="hg-nav-section">Configuration</div>
        @if($user?->canAnyAccounting(['company_setup.view','company_setup.manage']))<a href="{{ route('company-setup.edit') }}" class="{{ request()->routeIs('company-setup.*') ? 'active' : '' }}"><span class="hg-nav-icon">🏢</span><span class="hg-nav-text">Company Setup</span></a>@endif
        @if($user?->canAnyAccounting(['chart_of_accounts.view','chart_of_accounts.manage']))<a href="{{ route('chart-of-accounts.index', $user?->canAccounting('chart_of_accounts.view') ? [] : ['action' => 'add']) }}" class="{{ request()->routeIs('chart-of-accounts.*') ? 'active' : '' }}"><span class="hg-nav-icon">📘</span><span class="hg-nav-text">Chart of Accounts</span></a>@endif
        @if($user?->canAnyAccounting(['opening_balances.view','opening_balances.manage']))<a href="{{ route('opening-balances.index', $user?->canAccounting('opening_balances.view') ? [] : ['action' => 'add']) }}" class="{{ request()->routeIs('opening-balances.*') ? 'active' : '' }}"><span class="hg-nav-icon">📥</span><span class="hg-nav-text">Opening Balances</span></a>@endif
        @if($user?->canAnyAccounting(['accounting_rules.view','accounting_rules.manage']))<a href="{{ route('accounting-rules.index', $user?->canAccounting('accounting_rules.view') ? [] : ['action' => 'add']) }}" class="{{ request()->routeIs('accounting-rules.*') ? 'active' : '' }}"><span class="hg-nav-icon">⚙️</span><span class="hg-nav-text">Accounting Rules</span></a>@endif
        @if($user?->canAnyAccounting(['transaction_heads.view','transaction_heads.manage']))<a href="{{ route('transaction-heads.index', $user?->canAccounting('transaction_heads.view') ? [] : ['action' => 'add']) }}" class="{{ request()->routeIs('transaction-heads.*') ? 'active' : '' }}"><span class="hg-nav-icon">🏷️</span><span class="hg-nav-text">Transaction Heads</span></a>@endif
        @if($user?->canAnyAccounting(['parties.view','parties.manage']))<a href="{{ route('parties.index', $user?->canAccounting('parties.view') ? [] : ['action' => 'add']) }}" class="{{ request()->routeIs('parties.*') ? 'active' : '' }}"><span class="hg-nav-icon">👤</span><span class="hg-nav-text">Parties</span></a>@endif
        @if($user?->canAnyAccounting(['money_accounts.view','money_accounts.manage']))<a href="{{ route('money-accounts.index', $user?->canAccounting('money_accounts.view') ? [] : ['action' => 'add']) }}" class="{{ request()->routeIs('money-accounts.*') ? 'active' : '' }}"><span class="hg-nav-icon">🏦</span><span class="hg-nav-text">Money Accounts</span></a>@endif

        @if($canOpenOtherMasterData)
        <details class="hg-nav-group" data-hg-nav-group="other-master-data" @if($isOtherMasterDataRoute) open @endif>
            <summary class="{{ $isOtherMasterDataRoute ? 'active' : '' }}" aria-expanded="{{ $isOtherMasterDataRoute ? 'true' : 'false' }}">
                <span class="hg-nav-icon">📁</span>
                <span class="hg-nav-text">Other Master Data</span>
                <span class="hg-nav-caret">›</span>
            </summary>
            <div class="hg-nav-submenu">
                @if($user?->canAccounting('master_data.view'))
                <a href="{{ route('master.overview') }}" class="{{ request()->routeIs('master.overview') ? 'active' : '' }}"><span class="hg-nav-icon">▦</span><span class="hg-nav-text">Overview</span></a>
                @endif

                @if($user?->canAnyAccounting(['business_types.view','business_types.manage', 'currencies.view','currencies.manage', 'time_zones.view','time_zones.manage', 'financial_years.view','financial_years.manage']))
                <div class="hg-nav-subheading">Company Setup</div>
                @endif
                @if($user?->canAnyAccounting(['business_types.view','business_types.manage']))<a href="{{ route('master.business-types.index', $user?->canAccounting('business_types.view') ? [] : ['action' => 'add']) }}" class="{{ request()->routeIs('master.business-types.*') ? 'active' : '' }}"><span class="hg-nav-icon">🏢</span><span class="hg-nav-text">Business Types</span></a>@endif
                @if($user?->canAnyAccounting(['currencies.view','currencies.manage']))<a href="{{ route('master.currencies.index', $user?->canAccounting('currencies.view') ? [] : ['action' => 'add']) }}" class="{{ request()->routeIs('master.currencies.*') ? 'active' : '' }}"><span class="hg-nav-icon">৳</span><span class="hg-nav-text">Currencies</span></a>@endif
                @if($user?->canAnyAccounting(['time_zones.view','time_zones.manage']))<a href="{{ route('master.time-zones.index', $user?->canAccounting('time_zones.view') ? [] : ['action' => 'add']) }}" class="{{ request()->routeIs('master.time-zones.*') ? 'active' : '' }}"><span class="hg-nav-icon">◷</span><span class="hg-nav-text">Time Zones</span></a>@endif
                @if($user?->canAnyAccounting(['financial_years.view','financial_years.manage']))<a href="{{ route('master.financial-years.index', $user?->canAccounting('financial_years.view') ? [] : ['action' => 'add']) }}" class="{{ request()->routeIs('master.financial-years.*') ? 'active' : '' }}"><span class="hg-nav-icon">📅</span><span class="hg-nav-text">Financial Years</span></a>@endif

                @if($user?->canAnyAccounting(['party_types.view','party_types.manage', 'money_account_types.view','money_account_types.manage', 'transaction_categories.view','transaction_categories.manage', 'voucher_numbering.view','voucher_numbering.manage']))
                <div class="hg-nav-subheading">Accounting Masters</div>
                @endif
                @if($user?->canAnyAccounting(['party_types.view','party_types.manage']))<a href="{{ route('master.index', ['section' => 'party-types'] + ($user?->canAccounting('party_types.view') ? [] : ['action' => 'add'])) }}" class="{{ request()->routeIs('master.index') && request()->route('section') === 'party-types' ? 'active' : '' }}"><span class="hg-nav-icon">🧩</span><span class="hg-nav-text">Party Types</span></a>@endif
                @if($user?->canAnyAccounting(['money_account_types.view','money_account_types.manage']))<a href="{{ route('master.index', ['section' => 'money-account-types'] + ($user?->canAccounting('money_account_types.view') ? [] : ['action' => 'add'])) }}" class="{{ request()->routeIs('master.index') && request()->route('section') === 'money-account-types' ? 'active' : '' }}"><span class="hg-nav-icon">💳</span><span class="hg-nav-text">Money Account Types</span></a>@endif
                @if($user?->canAnyAccounting(['transaction_categories.view','transaction_categories.manage']))<a href="{{ route('master.index', ['section' => 'transaction-categories'] + ($user?->canAccounting('transaction_categories.view') ? [] : ['action' => 'add'])) }}" class="{{ request()->routeIs('master.index') && request()->route('section') === 'transaction-categories' ? 'active' : '' }}"><span class="hg-nav-icon">🗂️</span><span class="hg-nav-text">Transaction Types</span></a>@endif
                @if($user?->canAnyAccounting(['voucher_numbering.view','voucher_numbering.manage']))<a href="{{ route('master.voucher-sequences.index', $user?->canAccounting('voucher_numbering.view') ? [] : ['action' => 'add']) }}" class="{{ request()->routeIs('master.voucher-sequences.*') ? 'active' : '' }}"><span class="hg-nav-icon">🔢</span><span class="hg-nav-text">Voucher Numbering</span></a>@endif
            </div>
        </details>
        @endif
        @endif

        @if($canAnySystem)
        <div class="hg-nav-section">System</div>
        @if($user?->canAnyAccounting(['users.view','users.manage']))<a href="{{ route('system.users.index', $user?->canAccounting('users.view') ? [] : ['action' => 'add']) }}" class="{{ request()->routeIs('system.users.*') ? 'active' : '' }}"><span class="hg-nav-icon">👥</span><span class="hg-nav-text">Users</span></a>@endif
        @if($user?->canAnyAccounting(['role_matrix.view','role_matrix.manage']))<a href="{{ route('system.role-matrix.index', $user?->canAccounting('role_matrix.view') ? [] : ['action' => 'add']) }}" class="{{ request()->routeIs('system.role-matrix.*') ? 'active' : '' }}"><span class="hg-nav-icon">🛡️</span><span class="hg-nav-text">Role Matrix</span></a>@endif
        @if($user?->canAccounting('settings.manage'))<a href="{{ route('system.settings.index') }}" class="{{ request()->routeIs('system.settings.*') ? 'active' : '' }}"><span class="hg-nav-icon">🎨</span><span class="hg-nav-text">Branding Settings</span></a>@endif
        @endif
    </nav>
</aside>
