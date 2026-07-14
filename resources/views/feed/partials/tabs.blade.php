<nav class="feed-module-nav" aria-label="Feed module navigation">
    <div class="feed-module-nav-inner">
        @if(auth()->user()?->canAccounting('transactions.manage'))
            <a href="{{ route('feed.business-tracking.index') }}" class="{{ request()->routeIs('feed.business-tracking.*') ? 'active' : '' }}">◫ Business Tracking</a>
            <a href="{{ route('feed.purchases.create') }}" class="{{ request()->routeIs('feed.purchases.*') ? 'active' : '' }}">🛒 Feed Purchase</a>
            <a href="{{ route('feed.sales.create') }}" class="{{ request()->routeIs('feed.sales.*') ? 'active' : '' }}">🧾 Feed Sale</a>
        @endif
        @if(auth()->user()?->canAccounting('transactions.view'))
            <a href="{{ route('feed.inventory.index') }}" class="{{ request()->routeIs('feed.inventory.*') ? 'active' : '' }}">▦ Inventory</a>
        @endif
        @if(auth()->user()?->canAccounting('transactions.manage'))
            <a href="{{ route('feed.setup.index') }}" class="{{ request()->routeIs('feed.setup.*') ? 'active' : '' }}">⚙ Feed Setup</a>
        @endif
    </div>
</nav>
