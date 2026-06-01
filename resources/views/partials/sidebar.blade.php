@php
    use Illuminate\Support\Facades\Route;

    $currentUser = auth()->user();
    $routeName = request()->route()?->getName();
    $isActive = function ($name) use ($routeName) {
        if ($routeName === 'dashboard') {
            return '';
        }

        return ($routeName === $name || str_starts_with((string) $routeName, $name . '.')) ? 'active' : '';
    };

    $canRoute = fn ($name) => Route::has($name) && ($currentUser?->canViewRoute($name) ?? false);
    $canPermission = fn ($permission) => $currentUser?->hasPermission($permission) ?? false;

    $isMasterDataRoute = request()->routeIs('setup.master-data*');

    $setupLinks = [
        ['label' => 'Company Setup', 'route' => 'setup.company', 'icon' => '1'],
        ['label' => 'Chart of Accounts', 'route' => 'setup.chart-of-accounts', 'icon' => '2'],
        ['label' => 'Cash / Bank Setup', 'route' => 'setup.cash-bank-accounts', 'icon' => '3'],
        ['label' => 'Party / Person Setup', 'route' => 'setup.parties', 'icon' => '4'],
        ['label' => 'Transaction Head Setup', 'route' => 'setup.transaction-heads', 'icon' => '5'],
        ['label' => 'Accounting Rules Setup', 'route' => 'setup.accounting-rules-setup', 'icon' => '6'],
        ['label' => 'Opening Balance Setup', 'route' => 'setup.opening-balances', 'icon' => '7'],
        ['label' => 'Voucher Numbering', 'route' => 'setup.voucher-numbering', 'icon' => '8'],
    ];

    $masterDataLinks = [
        ['label' => 'Business Types', 'route' => 'setup.master-data.business-types'],
        ['label' => 'Currencies', 'route' => 'setup.master-data.currencies'],
        ['label' => 'Settlement Types', 'route' => 'setup.master-data.settlement-types'],
        ['label' => 'Party Types', 'route' => 'setup.master-data.party-types'],
        ['label' => 'Ledger Types', 'route' => 'setup.master-data.ledger-types'],
        ['label' => 'Financial Years', 'route' => 'setup.master-data.financial-years'],
    ];

    $isReportRoute = request()->routeIs('accounting-reports.*') || request()->routeIs('ledger-report.*') || request()->routeIs('audit-trail.*');

    $reportLinks = [
        ['label' => 'Transaction List', 'route' => 'accounting-reports.transactions.index', 'active' => 'accounting-reports.transactions.*'],
        ['label' => 'Cash / Bank Book', 'route' => 'accounting-reports.cash-bank-book.index', 'active' => 'accounting-reports.cash-bank-book.*'],
        ['label' => 'Ledger Report', 'route' => 'accounting-reports.ledger-report.index', 'active' => 'accounting-reports.ledger-report.*'],
        ['label' => 'Trial Balance', 'route' => 'accounting-reports.trial-balance.index', 'active' => 'accounting-reports.trial-balance.*'],
        ['label' => 'Income Statement', 'route' => 'accounting-reports.income-statement.index', 'active' => 'accounting-reports.income-statement.*'],
        ['label' => 'Balance Sheet', 'route' => 'accounting-reports.balance-sheet.index', 'active' => 'accounting-reports.balance-sheet.*'],
        ['label' => 'Cash Flow Statement', 'route' => 'accounting-reports.cash-flow-statement.index', 'active' => 'accounting-reports.cash-flow-statement.*'],
        ['label' => 'Customer Receivables', 'route' => 'accounting-reports.customer-receivables.index', 'active' => 'accounting-reports.customer-receivables.*'],
        ['label' => 'Supplier Payables', 'route' => 'accounting-reports.supplier-payables.index', 'active' => 'accounting-reports.supplier-payables.*'],
        ['label' => 'Sales Report', 'route' => 'accounting-reports.sales-report.index', 'active' => 'accounting-reports.sales-report.*'],
        ['label' => 'Expense Report', 'route' => 'accounting-reports.expense-report.index', 'active' => 'accounting-reports.expense-report.*'],
        ['label' => 'Audit Log Report', 'route' => 'audit-trail.index', 'active' => 'audit-trail.*'],
    ];

    $hasVisibleReportLinks = collect($reportLinks)->contains(fn ($link) => $canRoute($link['route']));

@endphp

<aside class="sidebar" id="appSidebar">
    <a href="{{ url('/') }}" class="brand brand-home" aria-label="Go to home">
        <div class="brand-mark">হি</div>
        <div>
            <h1>HisebGhor</h1>
            <p>Accounting System</p>
        </div>
    </a>

    @if(collect($setupLinks)->contains(fn ($link) => $canRoute($link['route'])) || $canRoute('setup.master-data'))
        <div class="nav-title">Setup</div>

        @foreach($setupLinks as $link)
            @if($canRoute($link['route']))
                <a href="{{ route($link['route']) }}" class="nav-item {{ $isActive($link['route']) }}">
                    <div class="nav-icon">{{ $link['icon'] }}</div>
                    <span>{{ $link['label'] }}</span>
                </a>
            @endif
        @endforeach

        @if($canRoute('setup.master-data'))
            <details class="nav-group master-data-nav-group" {{ $isMasterDataRoute ? 'open' : '' }}>
                <summary
                    class="nav-item nav-parent {{ $isActive('setup.master-data') }}"
                    data-sidebar-group-summary
                    aria-controls="masterDataSubmenu"
                >
                    <div class="nav-icon">9</div>
                    <span>Master Setup</span>
                    <span class="nav-arrow" aria-hidden="true">⌄</span>
                </summary>

                <div
                    class="nav-submenu {{ $isMasterDataRoute ? 'is-open' : '' }}"
                    id="masterDataSubmenu"
                    aria-label="Master Setup Submenu"
                >
                    @foreach($masterDataLinks as $masterLink)
                        @if($canRoute($masterLink['route']))
                            <a
                                href="{{ route($masterLink['route']) }}"
                                class="nav-subitem {{ request()->routeIs($masterLink['route']) ? 'active' : '' }}"
                            >
                                <span>{{ $masterLink['label'] }}</span>
                            </a>
                        @endif
                    @endforeach
                </div>
            </details>
        @endif
    @endif

    <div class="nav-title">Main Menu</div>

    @if($canRoute('transactions.create'))
        <a
            href="{{ route('transactions.create') }}"
            class="nav-item {{ $isActive('transactions.create') }}"
        >
            <div class="nav-icon">＋</div>
            <span>Add Transaction</span>
        </a>
    @endif

    @if($canRoute('manual-journals.index'))
        <a href="{{ route('manual-journals.index') }}" class="nav-item {{ $isActive('manual-journals.index') }}">
            <div class="nav-icon">JV</div>
            <span>Manual Journal</span>
        </a>
    @endif

    @if($hasVisibleReportLinks)
        <details class="nav-group reports-nav-group" {{ $isReportRoute ? 'open' : '' }}>
            <summary
                class="nav-item nav-parent {{ $isReportRoute ? 'active' : '' }}"
                data-sidebar-group-summary
                aria-controls="reportsSubmenu"
            >
                <div class="nav-icon">R</div>
                <span>Reports</span>
                <span class="nav-arrow" aria-hidden="true">⌄</span>
            </summary>

            <div
                class="nav-submenu {{ $isReportRoute ? 'is-open' : '' }}"
                id="reportsSubmenu"
                aria-label="Reports Submenu"
            >
                @foreach($reportLinks as $reportLink)
                    @if($canRoute($reportLink['route']))
                        <a
                            href="{{ route($reportLink['route']) }}"
                            class="nav-subitem {{ request()->routeIs($reportLink['active']) ? 'active' : '' }}"
                        >
                            <span>{{ $reportLink['label'] }}</span>
                        </a>
                    @endif
                @endforeach
            </div>
        </details>
    @endif

    @if($canRoute('due-management.index'))
        <a href="{{ route('due-management.index') }}" class="nav-item {{ $isActive('due-management.index') }}">
            <div class="nav-icon">DM</div>
            <span>Due Management</span>
        </a>
    @endif

    @if($canRoute('advance-management.index'))
        <a href="{{ route('advance-management.index') }}" class="nav-item {{ $isActive('advance-management.index') }}">
            <div class="nav-icon">AM</div>
            <span>Advance Management</span>
        </a>
    @endif

    @if($canRoute('approvals.index'))
        <a href="{{ route('approvals.index') }}" class="nav-item {{ $isActive('approvals.index') }}">
            <div class="nav-icon">✓</div>
            <span>Approvals</span>
        </a>
    @endif

    @if($canRoute('release-notes.index'))
        <div class="nav-title">System</div>

        <a href="{{ route('release-notes.index') }}" class="nav-item {{ $isActive('release-notes.index') }}">
            <div class="nav-icon">🚀</div>
            <span>Release Tracker</span>
        </a>
    @endif

    @if($canRoute('settings.users-roles'))
        <div class="nav-title">Settings</div>

        <a href="{{ route('settings.users-roles') }}" class="nav-item {{ $isActive('settings.users-roles') }}">
            <div class="nav-icon">U</div>
            <span>Users & Roles</span>
        </a>
    @endif

    <div class="help-card">
        <div class="help-badge">?</div>
        <div>
            <strong>Need Help?</strong>
            <span>Daily entry guide ↗</span>
        </div>
    </div>
</aside>
