@props([
    'resetRoute',
    'submitLabel' => 'Run',
    'resetLabel' => 'Reset',
])

<div {{ $attributes->merge(['class' => 'filter-actions']) }}>
    <button class="btn-primary" type="submit">{{ $submitLabel }}</button>
    <a class="button btn-ghost" href="{{ $resetRoute }}">{{ $resetLabel }}</a>
</div>
