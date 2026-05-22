@props([
    'title',
    'rows' => [],
])

<div {{ $attributes->merge(['class' => 'card report-info-card']) }}>
    <div class="report-info-title">{{ $title }}</div>
    <div class="compact-ratio-grid">
        @foreach($rows as $row)
            <span>{{ $row['label'] ?? '' }}</span>
            <strong>{{ $row['value'] ?? '—' }}</strong>
        @endforeach
    </div>

    @if(trim($slot) !== '')
        {{ $slot }}
    @endif
</div>
