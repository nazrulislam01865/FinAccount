@props([
    'label',
    'value',
    'note' => null,
    'tone' => 'primary',
])

@php
    $toneClass = match ($tone) {
        'success' => 'report-tone-success',
        'danger' => 'report-tone-danger',
        'warning' => 'report-tone-warning',
        'muted' => 'report-tone-muted',
        default => 'report-tone-primary',
    };
@endphp

<div {{ $attributes->merge(['class' => 'card stat-card']) }}>
    <small>{{ $label }}</small>
    <strong class="{{ $toneClass }}">{{ $value }}</strong>
    @if($note)
        <span>{{ $note }}</span>
    @endif
</div>
