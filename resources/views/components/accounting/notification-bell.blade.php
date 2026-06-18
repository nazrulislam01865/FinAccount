<div class="hg-notification-widget" id="hgNotificationWidget">
    <button
        type="button"
        class="hg-notification-bell"
        id="hgNotificationBell"
        aria-label="Open notifications"
        aria-expanded="false"
        aria-controls="hgNotificationPanel"
    >
        <span aria-hidden="true">🔔</span>
        <span class="hg-notification-count hg-hidden" id="hgNotificationCount">0</span>
    </button>

    <section class="hg-notification-panel hg-hidden" id="hgNotificationPanel" aria-label="Notifications">
        <header class="hg-notification-panel-head">
            <div>
                <strong>Notifications</strong>
                <small id="hgNotificationStatus">Loading…</small>
            </div>
            <button type="button" class="hg-notification-text-btn" id="hgMarkAllRead">Mark all read</button>
        </header>

        <div class="hg-notification-list" id="hgNotificationList">
            <div class="hg-notification-empty">Loading notifications…</div>
        </div>

        <footer class="hg-notification-panel-foot">
            <a href="{{ route('accounting.notifications.index') }}">View all notifications</a>
        </footer>
    </section>
</div>
