<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Landing Admin | HisebGhor')</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>
        :root{--landing-green:#00a86b;--landing-green-dark:#087a52;--landing-green-soft:#e9fff5;--landing-ink:#101828;--landing-muted:#667085;--landing-line:#e5e7eb;--landing-bg:#f8fafc;--landing-card:#fff;--landing-shadow:0 16px 38px rgba(16,24,40,.07)}
        body{background:var(--landing-bg)}
        .landing-admin-shell{min-height:100vh;display:grid;grid-template-columns:292px minmax(0,1fr);background:var(--landing-bg)}
        .landing-admin-sidebar{position:sticky;top:0;height:100vh;overflow-y:auto;background:#fff;border-right:1px solid var(--landing-line);padding:20px 16px;z-index:30}
        .landing-admin-brand{display:flex;align-items:center;gap:12px;padding:0 8px 20px;border-bottom:1px solid var(--landing-line)}
        .landing-admin-brand-mark{width:44px;height:44px;border-radius:14px;background:linear-gradient(135deg,var(--landing-green),#16a34a);color:#fff;display:grid;place-items:center;font-size:22px;font-weight:950;box-shadow:0 14px 25px rgba(0,168,107,.22)}
        .landing-admin-brand h1{margin:0;font-size:19px;letter-spacing:-.03em}.landing-admin-brand p{margin:3px 0 0;color:var(--landing-muted);font-size:12px}
        .landing-admin-nav-title{margin:22px 8px 10px;color:#344054;font-size:12px;font-weight:900;text-transform:uppercase;letter-spacing:.06em}
        .landing-admin-nav{display:grid;gap:7px}.landing-admin-link{display:flex;align-items:center;gap:12px;padding:12px 13px;border-radius:14px;color:#475467;font-size:14px;font-weight:800;border:1px solid transparent;transition:.14s ease}.landing-admin-link:hover{background:#f0fdf4;color:var(--landing-green-dark)}.landing-admin-link.active{background:var(--landing-green-soft);color:var(--landing-green-dark);border-color:#bbf7d0;box-shadow:inset 4px 0 0 var(--landing-green)}
        .landing-admin-icon{width:28px;height:28px;border-radius:999px;background:#eef2f6;color:#667085;display:grid;place-items:center;font-size:12px;font-weight:900;flex:0 0 auto}.landing-admin-link.active .landing-admin-icon{background:var(--landing-green);color:#fff}
        .landing-admin-main{min-width:0}.landing-admin-topbar{height:74px;background:#fff;border-bottom:1px solid var(--landing-line);display:flex;align-items:center;gap:14px;padding:0 28px;position:sticky;top:0;z-index:20}.landing-admin-menu-button{width:40px;height:40px;border:1px solid var(--landing-line);border-radius:12px;background:#fff;color:#344054;font-size:22px;display:none;place-items:center;cursor:pointer}.landing-admin-topbar h2{margin:0;font-size:19px;letter-spacing:-.02em}.landing-admin-topbar p{margin:2px 0 0;color:var(--landing-muted);font-size:12px}.landing-admin-top-actions{margin-left:auto;display:flex;align-items:center;gap:10px;flex-wrap:wrap}.landing-admin-content{padding:30px 34px 42px}.landing-admin-backdrop{display:none;position:fixed;inset:0;background:rgba(15,23,42,.45);z-index:25}.landing-admin-backdrop.show{display:block}
        .landing-dashboard-grid{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:16px;margin-bottom:18px}.landing-dashboard-card{background:#fff;border:1px solid var(--landing-line);border-radius:22px;box-shadow:var(--landing-shadow);padding:20px}.landing-dashboard-card small{display:block;color:var(--landing-muted);font-size:13px;font-weight:800;margin-bottom:7px}.landing-dashboard-card strong{font-size:28px;letter-spacing:-.04em;color:var(--landing-green-dark)}.landing-dashboard-card p{margin:8px 0 0;color:var(--landing-muted);font-size:13px;line-height:1.45}.landing-admin-panel{background:#fff;border:1px solid var(--landing-line);border-radius:22px;box-shadow:var(--landing-shadow);overflow:hidden}.landing-admin-panel-head{display:flex;align-items:flex-start;justify-content:space-between;gap:14px;padding:18px 20px;border-bottom:1px solid #eef2f7;background:linear-gradient(180deg,#fff,#fbfcfd)}.landing-admin-panel-head h3{margin:0;font-size:18px}.landing-admin-panel-head p{margin:5px 0 0;color:var(--landing-muted);font-size:13px}.landing-admin-panel-body{padding:20px}.landing-admin-quick-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:14px}.landing-admin-quick-card{border:1px solid var(--landing-line);border-radius:18px;padding:16px;background:#fbfcfd;display:flex;justify-content:space-between;gap:14px;align-items:center}.landing-admin-quick-card strong{display:block;margin-bottom:4px}.landing-admin-quick-card span{display:block;color:var(--landing-muted);font-size:12px}.landing-admin-table-wrap{overflow-x:auto}.landing-admin-table{width:100%;border-collapse:collapse;min-width:760px}.landing-admin-table th,.landing-admin-table td{text-align:left;padding:13px 12px;border-bottom:1px solid #eef2f7;font-size:13px}.landing-admin-table th{color:#475467;background:#f8fafc;font-weight:900}.landing-admin-status{display:inline-flex;align-items:center;border-radius:999px;padding:5px 9px;background:#f0fdf4;color:#067647;font-weight:900;font-size:12px}.landing-admin-status.muted{background:#f2f4f7;color:#475467}.landing-admin-status.warning{background:#fff7ed;color:#9a3412}.landing-admin-mobile-heading{display:none}
        @media(max-width:1180px){.landing-dashboard-grid{grid-template-columns:repeat(2,minmax(0,1fr))}.landing-admin-quick-grid{grid-template-columns:repeat(2,minmax(0,1fr))}}
        @media(max-width:880px){.landing-admin-shell{grid-template-columns:1fr}.landing-admin-sidebar{position:fixed;left:0;top:0;bottom:0;width:292px;transform:translateX(-105%);transition:transform .18s ease}.landing-admin-shell.sidebar-open .landing-admin-sidebar{transform:translateX(0)}.landing-admin-menu-button{display:grid}.landing-admin-content{padding:24px 16px 34px}.landing-admin-topbar{padding:0 16px}.landing-admin-top-actions .button:not(.keep-mobile){display:none}.landing-admin-mobile-heading{display:block}.landing-admin-backdrop.show{display:block}}
        @media(max-width:640px){.landing-dashboard-grid,.landing-admin-quick-grid{grid-template-columns:1fr}.landing-admin-topbar h2{font-size:16px}.landing-admin-topbar p{display:none}}
    </style>
    @stack('styles')
</head>
@php
    $currentUser = auth('landing_admin')->user();
    $sections = [
        ['label' => 'Basic Setup', 'section' => 'basic', 'icon' => 'B'],
        ['label' => 'Navigation', 'section' => 'nav', 'icon' => 'N'],
        ['label' => 'Hero Section', 'section' => 'hero', 'icon' => 'H'],
        ['label' => 'Why Section', 'section' => 'why', 'icon' => 'W'],
        ['label' => 'Feature Screens', 'section' => 'features', 'icon' => 'F'],
        ['label' => 'Audience', 'section' => 'audience', 'icon' => 'A'],
        ['label' => 'Pricing', 'section' => 'pricing', 'icon' => 'P'],
        ['label' => 'Testimonials', 'section' => 'testimonials', 'icon' => 'T'],
        ['label' => 'FAQ', 'section' => 'faq', 'icon' => 'Q'],
        ['label' => 'Contact', 'section' => 'contact', 'icon' => 'C'],
        ['label' => 'Footer', 'section' => 'footer', 'icon' => 'Ft'],
    ];
    $activeSection = request('section', 'basic');
@endphp
<body>
<div class="landing-admin-shell" id="landingAdminShell">
    <aside class="landing-admin-sidebar" id="landingAdminSidebar">
        <a href="{{ route('landing-admin.dashboard') }}" class="landing-admin-brand">
            <div class="landing-admin-brand-mark">হি</div>
            <div>
                <h1>HisebGhor</h1>
                <p>Landing Page Admin</p>
            </div>
        </a>

        <div class="landing-admin-nav-title">Dashboard</div>
        <nav class="landing-admin-nav">
            <a class="landing-admin-link {{ request()->routeIs('landing-admin.dashboard') ? 'active' : '' }}" href="{{ route('landing-admin.dashboard') }}">
                <span class="landing-admin-icon">D</span><span>Landing Dashboard</span>
            </a>
        </nav>

        <div class="landing-admin-nav-title">Landing Sections</div>
        <nav class="landing-admin-nav">
            @foreach($sections as $section)
                <a class="landing-admin-link {{ request()->routeIs('landing-admin.edit') && $activeSection === $section['section'] ? 'active' : '' }}" href="{{ route('landing-admin.edit', ['section' => $section['section']]) }}">
                    <span class="landing-admin-icon">{{ $section['icon'] }}</span><span>{{ $section['label'] }}</span>
                </a>
            @endforeach
        </nav>

        <div class="landing-admin-nav-title">Links</div>
        <nav class="landing-admin-nav">
            <a class="landing-admin-link" href="{{ route('landing.public') }}" target="_blank">
                <span class="landing-admin-icon">↗</span><span>Open Public Page</span>
            </a>
            <form method="POST" action="{{ route('landing-admin.logout') }}">
                @csrf
                <button type="submit" class="landing-admin-link" style="width:100%;border:1px solid transparent;background:transparent;cursor:pointer;text-align:left">
                    <span class="landing-admin-icon">⏻</span><span>Logout</span>
                </button>
            </form>
        </nav>
    </aside>

    <div class="landing-admin-backdrop" data-landing-admin-close></div>

    <main class="landing-admin-main">
        <header class="landing-admin-topbar">
            <button class="landing-admin-menu-button" type="button" data-landing-admin-menu aria-label="Open landing admin menu">☰</button>
            <div>
                <h2>@yield('page_heading', 'Landing Page Admin')</h2>
                <p>Dedicated dashboard for managing the public HisebGhor landing page.</p>
            </div>
            <div class="landing-admin-top-actions">
                <a href="{{ route('landing.show', ['preview' => 1]) }}" target="_blank" class="button btn-ghost keep-mobile">Preview</a>
                <a href="{{ route('landing.public') }}" target="_blank" class="button btn-outline">Public Page</a>
            </div>
        </header>

        <section class="landing-admin-content">
            @yield('content')
        </section>
    </main>
</div>

<div class="toast" id="toast">Saved successfully.</div>
<script>
(function () {
    const shell = document.getElementById('landingAdminShell');
    const menuButton = document.querySelector('[data-landing-admin-menu]');
    const backdrop = document.querySelector('[data-landing-admin-close]');

    function closeMenu() {
        shell?.classList.remove('sidebar-open');
        backdrop?.classList.remove('show');
    }

    menuButton?.addEventListener('click', function () {
        shell?.classList.toggle('sidebar-open');
        backdrop?.classList.toggle('show', shell?.classList.contains('sidebar-open'));
    });

    backdrop?.addEventListener('click', closeMenu);
    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape') {
            closeMenu();
        }
    });
})();
</script>
@stack('scripts')
</body>
</html>
