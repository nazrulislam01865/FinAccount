<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    @include('partials.head')
</head>
<body class="hg-body hg-sidebar-booting">
<div class="hg-app" id="hgAppShell" data-user-id="{{ (int) auth()->id() }}">
    @include('partials.accounting.sidebar')
    <button type="button" class="hg-sidebar-backdrop" data-hg-sidebar-close aria-label="Close navigation menu"></button>

    <main class="hg-main">
        <header class="hg-topbar">
            <button
                type="button"
                class="hg-sidebar-toggle"
                id="hgSidebarToggle"
                aria-controls="hgAccountingSidebar"
                aria-expanded="false"
                aria-label="Open navigation menu"
            >
                <span class="hg-sidebar-toggle-lines" aria-hidden="true"><span></span><span></span><span></span></span>
                <span class="hg-visually-hidden">Open navigation menu</span>
            </button>

            <div class="hg-topbar-context">
                @isset($topbar)
                    {{ $topbar }}
                @else
                    <div class="hg-topbar-title">{{ $title ?? 'HisebGhor' }}</div>
                @endisset
            </div>

            <div class="hg-topbar-account-controls" aria-label="Notification and account controls">
                <x-accounting.notification-bell />
                <x-accounting.user-menu />
            </div>
        </header>

        <div class="hg-content">
            @if (session('success'))<div class="hg-alert hg-alert-success">{{ session('success') }}</div>@endif
            @if (session('login_notice'))<div class="hg-alert hg-alert-success">{{ session('login_notice') }}</div>@endif
            @if (session('error'))<div class="hg-alert hg-alert-danger">{{ session('error') }}</div>@endif
            @if (session('warning'))<div class="hg-alert hg-alert-warning">{{ session('warning') }}</div>@endif
            @if ($errors->any() && ! $errors->profilePhoto->any() && ! $errors->passwordUpdate->any())
                <div class="hg-alert hg-alert-danger"><strong>Please correct the following:</strong><ul>@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
            @endif

            @php
                $layoutCompany = \App\Support\CompanyContext::company();
            @endphp
            @if($layoutCompany && (! $layoutCompany->isSetupComplete() || ! $layoutCompany->isActiveForPosting()))
                <div class="hg-alert hg-alert-warning">
                    <strong>{{ $layoutCompany->isSetupComplete() ? 'Company posting is inactive.' : 'Company Setup is incomplete.' }}</strong>
                    {{ $layoutCompany->isSetupComplete()
                        ? 'Activate the company before posting or updating transactions.'
                        : 'Select valid Business Type, Currency, Time Zone, and an Open Financial Year before posting transactions.' }}
                    @if(auth()->user()?->canAnyAccounting(['company_setup.view', 'company_setup.manage']))
                        <a href="{{ route('company-setup.edit') }}">Open Company Setup</a>
                    @endif
                </div>
            @endif

            {{ $slot }}
        </div>

        <x-accounting.footer />
    </main>
</div>

<x-accounting.safe-delete-modal />
<div class="hg-toast" id="hgNotificationToast" role="status" aria-live="polite"></div>

@php
    $pusherEnabled = filled(config('services.pusher.key'))
        && filled(config('services.pusher.secret'))
        && filled(config('services.pusher.app_id'))
        && filled(config('services.pusher.cluster'));
    $notificationAsset = public_path('js/hisebghor-notifications.js');
    $notificationAssetVersion = is_file($notificationAsset) ? filemtime($notificationAsset) : time();
@endphp
<script>
    window.HISEBGHOR_FORM_DRAFTS = {
        enabled: true,
        showUrlTemplate: @json(route('accounting.form-drafts.show', ['draftKey' => '__DRAFT_KEY__'])),
        storeUrlTemplate: @json(route('accounting.form-drafts.store', ['draftKey' => '__DRAFT_KEY__'])),
        destroyUrlTemplate: @json(route('accounting.form-drafts.destroy', ['draftKey' => '__DRAFT_KEY__']))
    };
</script>
<script>
    window.HISEBGHOR_NOTIFICATIONS = {
        userId: {{ (int) auth()->id() }},
        feedUrl: @json(route('accounting.notifications.feed')),
        readAllUrl: @json(route('accounting.notifications.read-all')),
        readUrlTemplate: @json(route('accounting.notifications.read', ['notification' => '__ID__'])),
        pusherAuthUrl: @json(route('accounting.notifications.pusher-auth')),
        pusherEnabled: @json($pusherEnabled),
        pusherKey: @json(config('services.pusher.key')),
        pusherCluster: @json(config('services.pusher.cluster')),
        pollIntervalMs: 60000
    };
</script>
@if($pusherEnabled)
    <script src="https://js.pusher.com/8.4.0/pusher.min.js"></script>
@endif
<script src="{{ asset('js/hisebghor-notifications.js') }}?v={{ $notificationAssetVersion }}"></script>
@php
    $sessionAsset = public_path('js/hisebghor-session-timeout.js');
    $sessionAssetVersion = is_file($sessionAsset) ? filemtime($sessionAsset) : time();
    $sessionTimeoutMinutes = (int) config('session.inactive_timeout', env('SESSION_INACTIVE_TIMEOUT', 15));
@endphp
<script>
    window.HISEBGHOR_SESSION = {
        timeoutMs: {{ max(1, $sessionTimeoutMinutes) * 60 * 1000 }},
        keepAliveUrl: @json(route('session.keep-alive')),
        timeoutUrl: @json(route('session.timeout')),
        loginUrl: @json(route('login')),
        storagePrefix: 'hisebghor.accounting.session'
    };
</script>
<script src="{{ asset('js/hisebghor-session-timeout.js') }}?v={{ $sessionAssetVersion }}"></script>
@stack('scripts')
</body>
</html>
