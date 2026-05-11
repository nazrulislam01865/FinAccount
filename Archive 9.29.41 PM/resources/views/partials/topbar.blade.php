<header class="topbar">
    <button class="menu-button" type="button">☰</button>
    <div class="global-search">
        <span>⌕</span>
        <input type="text" placeholder="Search transactions, reports, ledgers...">
    </div>
    <div class="top-actions">
        <div class="circle-icon">?</div>
        <div class="circle-icon">🔔<span class="notification-dot">3</span></div>
        <div class="avatar">{{ strtoupper(substr(auth()->user()?->name ?? 'AD', 0, 2)) }}</div>
        <span>{{ auth()->user()?->name ?? 'Admin User' }}</span>
        <form method="POST" action="{{ route('logout') }}" style="margin:0">
            @csrf
            <button type="submit" class="btn-ghost" style="min-height:34px;padding:8px 12px;border-radius:10px">Logout</button>
        </form>
    </div>
</header>
