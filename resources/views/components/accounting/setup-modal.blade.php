@props([
    'id',
    'title',
    'show' => false,
    'storeUrl',
    'createTitle',
])

<div
    id="{{ $id }}"
    class="hg-modal {{ $show ? 'show' : '' }}"
    data-setup-modal
    data-store-url="{{ $storeUrl }}"
    data-create-title="{{ $createTitle }}"
    aria-hidden="{{ $show ? 'false' : 'true' }}"
>
    <div class="hg-modal-box" role="dialog" aria-modal="true" aria-labelledby="{{ $id }}-title">
        <div class="hg-modal-head">
            <h2 id="{{ $id }}-title" data-setup-title>{{ $title }}</h2>
            <button type="button" class="hg-btn hg-btn-small" data-setup-close aria-label="Close">✕</button>
        </div>
        <div class="hg-modal-body">
            {{ $slot }}
        </div>
    </div>
</div>
