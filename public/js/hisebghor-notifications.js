(() => {
    'use strict';

    const config = window.HISEBGHOR_NOTIFICATIONS || {};
    const widget = document.getElementById('hgNotificationWidget');
    const userMenu = document.getElementById('hgUserMenu');

    if (!widget || !config.feedUrl) return;

    const bell = document.getElementById('hgNotificationBell');
    const panel = document.getElementById('hgNotificationPanel');
    const list = document.getElementById('hgNotificationList');
    const count = document.getElementById('hgNotificationCount');
    const status = document.getElementById('hgNotificationStatus');
    const markAll = document.getElementById('hgMarkAllRead');
    const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';
    let notifications = [];
    let unreadCount = 0;

    const escapeHtml = (value) => String(value ?? '')
        .replaceAll('&', '&amp;')
        .replaceAll('<', '&lt;')
        .replaceAll('>', '&gt;')
        .replaceAll('"', '&quot;')
        .replaceAll("'", '&#039;');

    function updateCount(value) {
        unreadCount = Math.max(0, Number(value) || 0);
        count.textContent = unreadCount > 99 ? '99+' : String(unreadCount);
        count.classList.toggle('hg-hidden', unreadCount === 0);
        status.textContent = unreadCount ? `${unreadCount} unread` : 'All caught up';
    }

    function formatTime(value) {
        if (!value) return 'Just now';
        const parsed = new Date(value);
        return Number.isNaN(parsed.getTime()) ? 'Just now' : parsed.toLocaleString();
    }

    function itemMarkup(notification) {
        const data = notification.data || {};
        const unread = !notification.read_at;
        const id = escapeHtml(notification.id || '');
        const url = data.url ? escapeHtml(data.url) : '';
        const openMarkup = url
            ? `<a href="${url}" class="hg-notification-open" data-notification-id="${id}">Open</a>`
            : '';

        return `
            <article class="hg-notification-item ${unread ? 'unread' : ''}" data-notification-id="${id}">
                <div class="hg-notification-item-icon">${escapeHtml(data.icon || '🔔')}</div>
                <div class="hg-notification-item-copy">
                    <strong>${escapeHtml(data.title || 'HisebGhor Notification')}</strong>
                    <p>${escapeHtml(data.message || '')}</p>
                    <small>${escapeHtml(notification.created_at_human || formatTime(notification.created_at))}</small>
                    <div class="hg-notification-item-actions">
                        ${openMarkup}
                        ${unread ? `<button type="button" data-mark-read="${id}">Mark read</button>` : ''}
                    </div>
                </div>
            </article>`;
    }

    function render() {
        if (!notifications.length) {
            list.innerHTML = '<div class="hg-notification-empty">No notifications yet.</div>';
            return;
        }
        list.innerHTML = notifications.map(itemMarkup).join('');
    }

    async function request(url, options = {}) {
        const response = await fetch(url, {
            credentials: 'same-origin',
            headers: {
                Accept: 'application/json',
                'X-CSRF-TOKEN': csrf,
                ...(options.headers || {}),
            },
            ...options,
        });

        if (!response.ok) {
            throw new Error(`Notification request failed (${response.status}).`);
        }

        return response.json();
    }

    async function refresh() {
        try {
            const payload = await request(config.feedUrl);
            notifications = Array.isArray(payload.notifications) ? payload.notifications : [];
            updateCount(payload.unread_count);
            render();
        } catch (error) {
            status.textContent = 'Unable to load';
            console.warn(error);
        }
    }

    async function markRead(id) {
        if (!id) return;
        const url = String(config.readUrlTemplate || '').replace('__ID__', encodeURIComponent(id));
        if (!url) return;

        const payload = await request(url, { method: 'POST' });
        notifications = notifications.map((item) => item.id === id
            ? { ...item, read_at: new Date().toISOString() }
            : item);
        updateCount(payload.unread_count);
        render();
    }

    async function markAllRead() {
        const payload = await request(config.readAllUrl, { method: 'POST' });
        notifications = notifications.map((item) => ({
            ...item,
            read_at: item.read_at || new Date().toISOString(),
        }));
        updateCount(payload.unread_count);
        render();
    }

    function showRealtimeToast(notification) {
        const data = notification.data || {};
        const toast = document.getElementById('hgNotificationToast');
        if (!toast) return;

        toast.textContent = `${data.icon || '🔔'} ${data.title || 'Notification'}: ${data.message || ''}`;
        toast.classList.add('show');
        window.setTimeout(() => toast.classList.remove('show'), 5000);
    }

    function addRealtime(notification) {
        if (!notification?.id || notifications.some((item) => item.id === notification.id)) return;
        notifications = [{ ...notification, created_at_human: 'Just now' }, ...notifications].slice(0, 15);
        updateCount(unreadCount + 1);
        render();
        showRealtimeToast(notification);
    }

    bell?.addEventListener('click', () => {
        const opening = panel.classList.contains('hg-hidden');
        panel.classList.toggle('hg-hidden', !opening);
        bell.setAttribute('aria-expanded', opening ? 'true' : 'false');
        if (opening) {
            userMenu?.removeAttribute('open');
            refresh();
        }
    });

    userMenu?.addEventListener('toggle', () => {
        if (userMenu.open) {
            panel.classList.add('hg-hidden');
            bell?.setAttribute('aria-expanded', 'false');
        }
    });

    document.addEventListener('click', (event) => {
        if (!widget.contains(event.target)) {
            panel.classList.add('hg-hidden');
            bell?.setAttribute('aria-expanded', 'false');
        }
        if (userMenu && !userMenu.contains(event.target)) {
            userMenu.removeAttribute('open');
        }
    });

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') {
            panel.classList.add('hg-hidden');
            bell?.setAttribute('aria-expanded', 'false');
            userMenu?.removeAttribute('open');
        }
    });

    list?.addEventListener('click', async (event) => {
        const markButton = event.target.closest('[data-mark-read]');
        const openLink = event.target.closest('.hg-notification-open');

        try {
            if (markButton) {
                const execution = window.HisebGhorExecution;
                if (execution && !execution.begin(markButton)) return;
                try {
                    await markRead(markButton.dataset.markRead);
                } finally {
                    execution?.end();
                }
            } else if (openLink) {
                event.preventDefault();
                await markRead(openLink.dataset.notificationId);
                window.location.assign(openLink.href);
            }
        } catch (error) {
            console.warn(error);
            if (markButton) markButton.disabled = false;
            if (openLink) window.location.assign(openLink.href);
        }
    });

    markAll?.addEventListener('click', async () => {
        const execution = window.HisebGhorExecution;
        if (execution && !execution.begin(markAll)) return;
        try {
            await markAllRead();
        } catch (error) {
            console.warn(error);
        } finally {
            execution?.end();
        }
    });

    document.querySelectorAll('.hg-page-mark-read').forEach((button) => {
        button.addEventListener('click', async () => {
            const execution = window.HisebGhorExecution;
            if (execution && !execution.begin(button)) return;
            try {
                await markRead(button.dataset.notificationId);
                button.closest('.hg-notification-page-item')?.classList.remove('unread');
                button.remove();
            } catch (error) {
                console.warn(error);
            } finally {
                execution?.end();
            }
        });
    });

    document.querySelectorAll('[data-notification-open]').forEach((link) => {
        link.addEventListener('click', async (event) => {
            event.preventDefault();
            try {
                await markRead(link.dataset.notificationOpen);
            } catch (error) {
                console.warn(error);
            }
            window.location.assign(link.href);
        });
    });

    if (config.pusherEnabled && window.Pusher && config.pusherKey) {
        try {
            const authHeaders = {
                'X-CSRF-TOKEN': csrf,
                Accept: 'application/json',
            };
            const pusher = new window.Pusher(config.pusherKey, {
                cluster: config.pusherCluster,
                forceTLS: true,
                authEndpoint: config.pusherAuthUrl,
                auth: { headers: authHeaders },
                channelAuthorization: {
                    endpoint: config.pusherAuthUrl,
                    headers: authHeaders,
                },
            });

            pusher.subscribe(`private-hisebghor.user.${config.userId}`)
                .bind('hisebghor-notification', addRealtime);
        } catch (error) {
            console.warn('Pusher notifications could not start. Polling remains active.', error);
        }
    }

    refresh();
    window.setInterval(refresh, Number(config.pollIntervalMs) || 60000);
    document.addEventListener('visibilitychange', () => {
        if (!document.hidden) refresh();
    });
})();
