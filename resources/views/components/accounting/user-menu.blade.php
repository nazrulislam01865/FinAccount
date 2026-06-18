@php
    $accountUser = auth()->user();
    $profilePhotoPath = trim((string) ($accountUser?->profile_photo_path ?? ''));
    $profilePhotoUrl = $profilePhotoPath !== ''
        ? route('accounting.profile.photo', ['v' => optional($accountUser?->updated_at)->timestamp])
        : null;
@endphp

<details class="hg-user-menu" id="hgUserMenu">
    <summary class="hg-user-menu-trigger" aria-label="Open account menu">
        <span class="hg-account-avatar" aria-hidden="true">
            @if($profilePhotoUrl)
                <img src="{{ $profilePhotoUrl }}" alt="{{ $accountUser?->name }} profile picture">
            @else
                {{ $accountUser?->initials() ?: 'U' }}
            @endif
        </span>
        <span class="hg-user-menu-copy">
            <strong>{{ $accountUser?->name ?? 'User' }}</strong>
            <small>{{ $accountUser?->roleLabel() ?? 'My Account' }}</small>
        </span>
        <span class="hg-user-menu-arrow" aria-hidden="true">▾</span>
    </summary>

    <div class="hg-user-menu-panel">
        <div class="hg-user-menu-head">
            <span class="hg-account-avatar large" aria-hidden="true">
                @if($profilePhotoUrl)
                    <img src="{{ $profilePhotoUrl }}" alt="{{ $accountUser?->name }} profile picture">
                @else
                    {{ $accountUser?->initials() ?: 'U' }}
                @endif
            </span>
            <div>
                <strong>{{ $accountUser?->name ?? 'User' }}</strong>
                <small>{{ $accountUser?->email ?? '' }}</small>
            </div>
        </div>

        <a href="{{ route('accounting.profile') }}" class="hg-user-menu-link">
            <span aria-hidden="true">👤</span>
            <span>My Profile</span>
        </a>
        <a href="{{ route('accounting.profile') }}#change-password" class="hg-user-menu-link">
            <span aria-hidden="true">🔐</span>
            <span>Change Password</span>
        </a>

        <form method="POST" action="{{ route('logout') }}" class="hg-user-menu-logout">
            @csrf
            <button type="submit">
                <span aria-hidden="true">↪</span>
                <span>Logout</span>
            </button>
        </form>
    </div>
</details>
