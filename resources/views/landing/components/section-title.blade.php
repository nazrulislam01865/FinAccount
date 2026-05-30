<div class="section-title">
  @if(!empty($mini))
    <div class="mini" data-bn="{{ $txt($mini, 'bn') }}" data-en="{{ $txt($mini, 'en') }}">{{ $txt($mini, $defaultLang) }}</div>
  @endif
  <h2 data-bn="{{ $txt($title ?? '', 'bn') }}" data-en="{{ $txt($title ?? '', 'en') }}">{{ $txt($title ?? '', $defaultLang) }}</h2>
  @if(!empty($subtitle))
    <p data-bn="{{ $txt($subtitle, 'bn') }}" data-en="{{ $txt($subtitle, 'en') }}">{{ $txt($subtitle, $defaultLang) }}</p>
  @endif
</div>
