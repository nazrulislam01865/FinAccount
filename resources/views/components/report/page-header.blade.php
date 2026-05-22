@props([
    'title',
    'subtitle' => null,
])

<div {{ $attributes->merge(['class' => 'page-title report-page-header']) }}>
    <div>
        <h2>{{ $title }}</h2>
        @if($subtitle)
            <p>{{ $subtitle }}</p>
        @endif
    </div>

    @if(trim($actions ?? '') !== '')
        <div class="quick-actions">
            {{ $actions }}
        </div>
    @endif
</div>
