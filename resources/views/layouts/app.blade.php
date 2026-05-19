<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>@yield('title', 'Accounting System')</title>


    @vite(['resources/css/app.css', 'resources/js/app.js'])

    @stack('styles')
</head>
@php
    $currentUser = auth()->user();
    $currentManagePermission = $currentUser?->managePermissionForRoute(request()->route()?->getName());
    $isReadOnlyFeature = $currentManagePermission && $currentUser?->hasPermission($currentManagePermission) !== true;
@endphp
<body class="sidebar-booting {{ $isReadOnlyFeature ? 'is-read-only-feature' : '' }}">
<div class="app" id="appShell">
    <script>
        (function () {
            try {
                var app = document.getElementById('appShell');
                var isMobile = window.matchMedia('(max-width: 880px)').matches;
                var isCollapsed = localStorage.getItem('accounting-sidebar-collapsed') === '1';

                if (app && isCollapsed && !isMobile) {
                    app.classList.add('sidebar-collapsed');
                }
            } catch (error) {
                // Keep the default sidebar state if storage is unavailable.
            }
        })();
    </script>
    @include('partials.sidebar')
    <script>
        (function () {
            try {
                var sidebar = document.getElementById('appSidebar');
                var savedScrollTop = Number(sessionStorage.getItem('accounting-sidebar-scroll-top') || 0);

                if (sidebar && Number.isFinite(savedScrollTop) && savedScrollTop > 0) {
                    sidebar.scrollTop = savedScrollTop;
                }
            } catch (error) {
                // Ignore storage access issues.
            }
        })();
    </script>
    <div class="sidebar-backdrop" data-sidebar-close aria-hidden="true"></div>

    <main class="main">
        @include('partials.topbar')

        <section class="content">
            @if($isReadOnlyFeature)
                <div class="card" style="padding:14px 18px;margin-bottom:18px;border-color:#fed7aa;background:#fff7ed;color:#9a3412;font-weight:750">
                    Your role can view this feature, but create/update/delete controls are locked.
                </div>
            @endif
            @yield('content')
        </section>
    </main>
</div>

<div class="toast" id="toast">Saved successfully.</div>

<script>
(function () {
    const csrfToken = () => document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';

    const showToast = (message) => {
        if (window.AccountingUI?.showToast) {
            window.AccountingUI.showToast(message);
            return;
        }

        const toast = document.getElementById('toast');

        if (!toast) {
            alert(message);
            return;
        }

        toast.textContent = message;
        toast.classList.add('show');
        setTimeout(() => toast.classList.remove('show'), 2600);
    };

    const refreshResultCount = (table) => {
        const resultCount = document.getElementById('resultCount');

        if (!table || !resultCount) {
            return;
        }

        const rows = Array.from(table.querySelectorAll('tbody tr:not([data-empty="true"])'));
        const visibleRows = rows.filter((row) => row.style.display !== 'none');

        resultCount.textContent = `Showing ${visibleRows.length} of ${rows.length} entries`;
    };

    if (document.body.classList.contains('is-read-only-feature')) {
        document.querySelectorAll('form[data-frontend-form]').forEach((form) => {
            form.querySelectorAll('input, select, textarea, button[type="submit"]').forEach((field) => {
                field.disabled = true;
            });
        });

        document.querySelectorAll('form[data-delete-form] button[type="submit"], .delete-btn').forEach((button) => {
            button.disabled = true;
            button.title = 'Read-only for your role';
        });
    }

    document.addEventListener('submit', async (event) => {
        const form = event.target.closest('form[data-delete-form]');

        if (!form || event.defaultPrevented) {
            return;
        }

        event.preventDefault();

        const submitButton = event.submitter || form.querySelector('button[type="submit"]');
        const originalText = submitButton?.textContent;

        if (submitButton) {
            submitButton.disabled = true;
            submitButton.textContent = '…';
        }

        try {
            const response = await fetch(form.action, {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': csrfToken(),
                    'X-Requested-With': 'XMLHttpRequest',
                },
                credentials: 'same-origin',
                body: new FormData(form),
            });

            const data = await response.json().catch(() => ({}));

            if (!response.ok || data.success === false) {
                throw new Error(data.message || 'Delete failed. Please try again.');
            }

            const row = form.closest('tr');
            const table = row?.closest('table');

            if (row) {
                row.remove();
                refreshResultCount(table);
            } else {
                window.location.reload();
                return;
            }

            showToast(data.message || 'Deleted successfully.');
        } catch (error) {
            showToast(error.message || 'Delete failed. Please try again.');

            if (submitButton) {
                submitButton.disabled = false;
                submitButton.textContent = originalText;
            }
        }
    });
})();
</script>

@stack('scripts')
</body>
</html>
