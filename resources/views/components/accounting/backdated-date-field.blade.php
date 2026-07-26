@props([
    'context',
    'value' => null,
    'variant' => 'accounting',
    'backdated' => false,
    'inputId' => 'transaction_date',
    'label' => 'Date',
])

@php
    $isFeed = $variant === 'feed';
    $fieldClass = $isFeed ? 'feed-field' : 'hg-field';
    $controlClass = $isFeed ? 'feed-control' : '';
    $requiredClass = $isFeed ? 'feed-req' : 'hg-required';
    $helpClass = $isFeed ? 'feed-help' : 'hg-field-help';
    $errorClass = $isFeed ? 'feed-warning-text' : 'hg-field-error';
    $today = (string) ($context['today'] ?? now()->toDateString());
    $normalDate = (string) ($context['default'] ?? $today);
    $selectedDate = (string) old('transaction_date', $value ?? $normalDate);
    $selectedIsPast = $selectedDate !== '' && $selectedDate < $today;
    $backdatedChecked = (bool) old('is_backdated', $backdated || $selectedIsPast);
    $ranges = $context['ranges'] ?? [];
    $rangeSummary = collect($ranges)
        ->map(fn (array $range): string => ($range['name'] ?? 'Open period').' ('.$range['start'].' to '.$range['end'].')')
        ->implode(', ');
    $toggleId = $inputId.'_backdated';
    $openMin = (string) ($context['min'] ?? '');
    $openMax = (string) ($context['max'] ?? '');
    $backdatedMax = $openMax !== '' && $today !== ''
        ? min($openMax, $today)
        : ($openMax !== '' ? $openMax : $today);
    $initialMin = $backdatedChecked ? $openMin : $normalDate;
    $initialMax = $backdatedChecked ? $backdatedMax : $normalDate;
@endphp

<div class="{{ $fieldClass }} hg-backdated-date-field {{ $backdatedChecked ? 'is-backdated' : 'is-date-locked' }}" data-backdated-entry>
    <div class="hg-backdated-date-header">
        <label for="{{ $inputId }}">{{ $label }} <span class="{{ $requiredClass }}">*</span></label>
        <label class="hg-checkbox-label hg-backdated-toggle" for="{{ $toggleId }}">
            <input type="hidden" name="is_backdated" value="0">
            <input
                id="{{ $toggleId }}"
                name="is_backdated"
                type="checkbox"
                value="1"
                data-backdated-toggle
                @checked($backdatedChecked)
                @disabled(! ($context['backdated_enabled'] ?? false) && ! $selectedIsPast)
            >
            <span>Backdated Entry</span>
        </label>
    </div>

    <input
        class="{{ $controlClass }}"
        id="{{ $inputId }}"
        name="transaction_date"
        type="date"
        value="{{ $selectedDate }}"
        @if($initialMin !== '') min="{{ $initialMin }}" @endif
        @if($initialMax !== '') max="{{ $initialMax }}" @endif
        data-backdated-date
        data-today="{{ $today }}"
        data-normal-date="{{ $normalDate }}"
        data-open-period-min="{{ $openMin }}"
        data-open-period-max="{{ $openMax }}"
        data-backdated-max="{{ $backdatedMax }}"
        data-open-period-ranges="{{ json_encode($ranges, JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE) }}"
        aria-readonly="{{ $backdatedChecked ? 'false' : 'true' }}"
        @readonly(! $backdatedChecked)
        required
    >

    <div class="{{ $helpClass }}" data-backdated-help>
        @if($rangeSummary !== '')
            Available open periods: {{ $rangeSummary }}.
        @else
            No active open financial year is available for posting.
        @endif
    </div>
    @error('transaction_date')<div class="{{ $errorClass }}">{{ $message }}</div>@enderror
    @error('is_backdated')<div class="{{ $errorClass }}">{{ $message }}</div>@enderror
</div>
