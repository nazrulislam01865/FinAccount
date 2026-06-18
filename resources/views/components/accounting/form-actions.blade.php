@props([
    'submitLabel' => 'Save',
])

<div {{ $attributes->class(['hg-form-actions']) }}>
    <div class="hg-actions">
        {{ $slot }}
        <button type="button" class="hg-btn hg-btn-draft" data-draft-save>
            <span aria-hidden="true">📝</span>
            Save Draft
        </button>
        <button type="submit" class="hg-btn hg-btn-primary">
            {{ $submitLabel }}
        </button>
    </div>
    <div class="hg-draft-feedback" data-draft-feedback role="status" aria-live="polite" hidden>
        <span data-draft-message></span>
        <button type="button" class="hg-draft-discard" data-draft-discard hidden>Discard draft</button>
    </div>
</div>
