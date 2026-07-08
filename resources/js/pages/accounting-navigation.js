const shell = document.getElementById('hgAppShell');
const sidebar = document.getElementById('hgAccountingSidebar');
const toggle = document.getElementById('hgSidebarToggle');
const closeControls = document.querySelectorAll('[data-hg-sidebar-close]');

if (shell && sidebar && toggle) {
    const mobileQuery = window.matchMedia('(max-width: 900px)');
    const userId = shell.dataset.userId || 'guest';
    const scrollStorageKey = `hisebghor.accounting.sidebar.scroll.${userId}`;
    const groupStorageKey = `hisebghor.accounting.sidebar.groups.${userId}`;
    let scrollFrame = null;
    let lastFocusedElement = null;
    let isRestoringGroupState = false;

    const storage = {
        get(key) {
            try {
                return window.sessionStorage.getItem(key);
            } catch {
                return null;
            }
        },
        set(key, value) {
            try {
                window.sessionStorage.setItem(key, value);
            } catch {
                // The navigation must remain usable when browser storage is disabled.
            }
        },
    };

    function disableSidebarTransitionsForNavigation() {
        document.body.classList.add('hg-sidebar-navigating');
    }

    function finishSidebarBoot() {
        window.requestAnimationFrame(() => {
            document.body.classList.remove('hg-sidebar-booting');
            window.requestAnimationFrame(() => {
                document.body.classList.remove('hg-sidebar-navigating');
            });
        });
    }

    function saveScrollPosition() {
        storage.set(scrollStorageKey, String(Math.max(0, sidebar.scrollTop)));
    }

    function restoreScrollPosition() {
        const savedPosition = Number.parseInt(storage.get(scrollStorageKey) || '', 10);

        if (Number.isFinite(savedPosition) && savedPosition >= 0) {
            window.requestAnimationFrame(() => {
                sidebar.scrollTop = savedPosition;
                window.requestAnimationFrame(() => {
                    sidebar.scrollTop = savedPosition;
                });
            });
            return;
        }

        const activeLink = sidebar.querySelector('.hg-nav a.active');
        if (!activeLink) return;

        window.requestAnimationFrame(() => {
            const sidebarRect = sidebar.getBoundingClientRect();
            const activeRect = activeLink.getBoundingClientRect();

            if (activeRect.top < sidebarRect.top || activeRect.bottom > sidebarRect.bottom) {
                sidebar.scrollTop += activeRect.top - sidebarRect.top - (sidebar.clientHeight / 3);
            }
        });
    }

    function saveGroupState() {
        if (isRestoringGroupState) return;

        const openGroups = Array.from(sidebar.querySelectorAll('details[data-hg-nav-group][open]'))
            .map((group) => group.dataset.hgNavGroup)
            .filter(Boolean);

        storage.set(groupStorageKey, JSON.stringify(openGroups));
    }

    function restoreGroupState() {
        const rawState = storage.get(groupStorageKey);
        if (!rawState) return;

        isRestoringGroupState = true;

        try {
            const openGroups = new Set(JSON.parse(rawState));

            sidebar.querySelectorAll('details[data-hg-nav-group]').forEach((group) => {
                const groupName = group.dataset.hgNavGroup;
                if (!groupName) return;

                const hasActiveItem = Boolean(group.querySelector('summary.active, a.active'));

                // Never close the group that contains the current page. Closing it after
                // the server renders it open is what creates the visible submenu flash.
                if (hasActiveItem || openGroups.has(groupName)) {
                    group.open = true;
                }
            });
        } catch {
            // Ignore invalid state left by an older application version.
        } finally {
            window.requestAnimationFrame(() => {
                isRestoringGroupState = false;
            });
        }
    }

    function setMenuState(open, { restoreFocus = true } = {}) {
        const shouldOpen = Boolean(open && mobileQuery.matches);

        shell.classList.toggle('hg-sidebar-open', shouldOpen);
        document.body.classList.toggle('hg-sidebar-open', shouldOpen);
        toggle.setAttribute('aria-expanded', shouldOpen ? 'true' : 'false');
        sidebar.setAttribute('aria-hidden', mobileQuery.matches && !shouldOpen ? 'true' : 'false');
        sidebar.inert = Boolean(mobileQuery.matches && !shouldOpen);

        if (shouldOpen) {
            lastFocusedElement = document.activeElement;
            window.requestAnimationFrame(() => {
                sidebar.querySelector('[data-hg-sidebar-close]')?.focus({ preventScroll: true });
            });
        } else if (restoreFocus && lastFocusedElement instanceof HTMLElement) {
            lastFocusedElement.focus({ preventScroll: true });
            lastFocusedElement = null;
        }
    }

    function syncResponsiveState() {
        if (mobileQuery.matches) {
            setMenuState(false, { restoreFocus: false });
        } else {
            shell.classList.remove('hg-sidebar-open');
            document.body.classList.remove('hg-sidebar-open');
            toggle.setAttribute('aria-expanded', 'false');
            sidebar.setAttribute('aria-hidden', 'false');
            sidebar.inert = false;
        }
    }

    toggle.addEventListener('click', () => {
        setMenuState(!shell.classList.contains('hg-sidebar-open'));
    });

    closeControls.forEach((control) => {
        control.addEventListener('click', () => setMenuState(false));
    });

    sidebar.addEventListener('scroll', () => {
        if (scrollFrame !== null) return;

        scrollFrame = window.requestAnimationFrame(() => {
            saveScrollPosition();
            scrollFrame = null;
        });
    }, { passive: true });

    sidebar.querySelectorAll('details[data-hg-nav-group]').forEach((group) => {
        group.addEventListener('toggle', saveGroupState);
    });

    sidebar.querySelectorAll('a[href]').forEach((link) => {
        link.addEventListener('click', () => {
            disableSidebarTransitionsForNavigation();
            saveScrollPosition();
            saveGroupState();

            if (mobileQuery.matches) {
                setMenuState(false, { restoreFocus: false });
            }
        });
    });

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && shell.classList.contains('hg-sidebar-open')) {
            setMenuState(false);
            return;
        }

        if (event.key !== 'Tab' || !shell.classList.contains('hg-sidebar-open')) return;

        const focusable = Array.from(sidebar.querySelectorAll(
            'a[href], button:not([disabled]), summary, [tabindex]:not([tabindex="-1"])',
        )).filter((element) => element.offsetParent !== null);

        if (!focusable.length) return;

        const first = focusable[0];
        const last = focusable[focusable.length - 1];

        if (event.shiftKey && document.activeElement === first) {
            event.preventDefault();
            last.focus();
        } else if (!event.shiftKey && document.activeElement === last) {
            event.preventDefault();
            first.focus();
        }
    });

    window.addEventListener('pagehide', () => {
        disableSidebarTransitionsForNavigation();
        saveScrollPosition();
        saveGroupState();
    });

    if (typeof mobileQuery.addEventListener === 'function') {
        mobileQuery.addEventListener('change', syncResponsiveState);
    } else {
        mobileQuery.addListener(syncResponsiveState);
    }

    restoreGroupState();
    restoreScrollPosition();
    syncResponsiveState();
    finishSidebarBoot();
} else {
    document.body?.classList.remove('hg-sidebar-booting');
}
