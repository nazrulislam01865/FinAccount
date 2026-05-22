@props([
    'title',
    'subtitle' => null,
    'badge' => null,
    'badgeClass' => 'badge-primary',
    'footerLeft' => null,
    'footerRight' => null,
])

<div {{ $attributes->merge(['class' => 'card table-card']) }}>
    <div class="card-head">
        <div>
            <h3>{{ $title }}</h3>
            @if($subtitle)
                <p>{{ $subtitle }}</p>
            @endif
        </div>
        @if($badge)
            <span class="badge {{ $badgeClass }}">{{ $badge }}</span>
        @endif
    </div>

    {{ $slot }}

    @if($footerLeft || $footerRight)
        <div class="table-footer">
            <span>{{ $footerLeft }}</span>
            <span>{{ $footerRight }}</span>
        </div>
    @endif
</div>
