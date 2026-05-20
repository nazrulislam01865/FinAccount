@php
    use Illuminate\Support\Facades\Route;

    $currentUser = auth()->user();
    $routeName = request()->route()?->getName();
    $isActive = function ($name) use ($routeName) {
        if ($routeName === 'dashboard' && $name === 'setup.company') {
            return 'active';
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
        ['label' => 'Ledger Mapping', 'route' => 'setup.ledger-mapping', 'icon' => '6'],
        ['label' => 'Opening Balance Setup', 'route' => 'setup.opening-balances', 'icon' => '7'],
        ['label' => 'Voucher Numbering', 'route' => 'setup.voucher-numbering', 'icon' => '8'],
    ];

    $masterDataLinks = [
        ['label' => 'Business Types', 'route' => 'setup.master-data.business-types'],
        ['label' => 'Currencies', 'route' => 'setup.master-data.currencies'],
        ['label' => 'Settlement Types', 'route' => 'setup.master-data.settlement-types'],
        ['label' => 'Party Types', 'route' => 'setup.master-data.party-types'],
        ['label' => 'Financial Years', 'route' => 'setup.master-data.financial-years'],
    ];
@endphp

<aside class="sidebar" id="appSidebar">
    <a href="{{ url('/') }}" class="brand brand-home" aria-label="Go to home">
        <div class="brand-mark">▥</div>
        <div>
            <h1>FinAcco</h1>
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
                    <div class="nav-icon">M</div>
                    <span>Master Data</span>
                    <span class="nav-arrow" aria-hidden="true">⌄</span>
                </summary>

                <div
                    class="nav-submenu {{ $isMasterDataRoute ? 'is-open' : '' }}"
                    id="masterDataSubmenu"
                    aria-label="Master Data Submenu"
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

    @if($canPermission('transactions.view'))
        <a href="#" class="nav-item">
            <div class="nav-icon">📄</div>
            <span>Transaction List</span>
        </a>
    @endif

    @if($canRoute('due-management.index'))
        <a href="{{ route('due-management.index') }}" class="nav-item {{ $isActive('due-management.index') }}">
            <div class="nav-icon">⏳</div>
            <span>Due Management</span>
        </a>
    @endif

    @if($canRoute('ledger-report.index'))
        <a href="{{ route('ledger-report.index') }}" class="nav-item {{ $isActive('ledger-report.index') }}">
            <div class="nav-icon">📘</div>
            <span>Ledger Report</span>
        </a>
    @endif

    @if($canPermission('cash-bank-book.view'))
        <a href="#" class="nav-item">
            <div class="nav-icon">🏦</div>
            <span>Cash / Bank Book</span>
        </a>
    @endif

    @if($canPermission('reports.view'))
        <a href="#" class="nav-item">
            <div class="nav-icon">▣</div>
            <span>Reports</span>
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
