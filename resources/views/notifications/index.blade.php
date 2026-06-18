<x-layouts::accounting title="Notifications">
    <section class="hg-page-header">
        <div>
            <h1>Notifications</h1>
            <p>Review real-time accounting activity alerts for your company.</p>
        </div>
        @if($notifications->count() > 0)
            <form method="POST" action="{{ route('accounting.notifications.read-all') }}">
                @csrf
                <button type="submit" class="hg-btn">Mark all as read</button>
            </form>
        @endif
    </section>

    <section class="hg-card hg-notification-center-page">
        @if($notifications->count() === 0)
            <div class="hg-notification-empty large">No notifications yet.</div>
        @else
            <div class="hg-notification-page-list">
                @foreach($notifications as $notification)
                    @php
                        $data = $notification->data;
                    @endphp
                    <article class="hg-notification-page-item {{ $notification->read_at ? '' : 'unread' }}">
                        <div class="hg-notification-page-icon">{{ $data['icon'] ?? '🔔' }}</div>
                        <div class="hg-notification-page-copy">
                            <div class="hg-notification-page-title-row">
                                <strong>{{ $data['title'] ?? 'HisebGhor Notification' }}</strong>
                                @if(! $notification->read_at)
                                    <span class="hg-status pending">Unread</span>
                                @endif
                            </div>
                            <p>{{ $data['message'] ?? '' }}</p>
                            <small>{{ optional($notification->created_at)->diffForHumans() }}</small>
                        </div>
                        <div class="hg-notification-page-actions">
                            @if(!empty($data['url']))
                                <a class="hg-btn hg-btn-small" href="{{ $data['url'] }}" data-notification-open="{{ $notification->id }}">Open</a>
                            @endif
                            @if(! $notification->read_at)
                                <button
                                    type="button"
                                    class="hg-btn hg-btn-small hg-page-mark-read"
                                    data-notification-id="{{ $notification->id }}"
                                >Mark read</button>
                            @endif
                        </div>
                    </article>
                @endforeach
            </div>

            <div class="hg-notification-pagination">
                {{ $notifications->links() }}
            </div>
        @endif
    </section>
</x-layouts::accounting>
