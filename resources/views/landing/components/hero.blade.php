<section class="hero">
  <div class="container hero-grid">
    <div>
      <div class="eyebrow" data-bn="{{ $txt(data_get($landing, 'hero.eyebrow'), 'bn') }}" data-en="{{ $txt(data_get($landing, 'hero.eyebrow'), 'en') }}">{{ $txt(data_get($landing, 'hero.eyebrow'), $defaultLang) }}</div>
      <h1 data-bn="{{ $txt(data_get($landing, 'hero.title'), 'bn') }}" data-en="{{ $txt(data_get($landing, 'hero.title'), 'en') }}">{{ $txt(data_get($landing, 'hero.title'), $defaultLang) }}</h1>
      <p data-bn="{{ $txt(data_get($landing, 'hero.subtitle'), 'bn') }}" data-en="{{ $txt(data_get($landing, 'hero.subtitle'), 'en') }}">{{ $txt(data_get($landing, 'hero.subtitle'), $defaultLang) }}</p>
      <div class="hero-buttons">
        @foreach(data_get($landing, 'hero.buttons', []) as $button)
          <a class="{{ $buttonClass(data_get($button, 'style')) }}" href="{{ $landingHref(data_get($button, 'href', '#contact'), data_get($button, 'label')) }}" data-bn="{{ $txt(data_get($button, 'label'), 'bn') }}" data-en="{{ $txt(data_get($button, 'label'), 'en') }}">{{ $txt(data_get($button, 'label'), $defaultLang) }}</a>
        @endforeach
      </div>
      <div class="trust-row">
        @foreach(data_get($landing, 'trust_items', []) as $item)
          <div class="trust"><span class="tick">✓</span><span data-bn="{{ $txt($item, 'bn') }}" data-en="{{ $txt($item, 'en') }}">{{ $txt($item, $defaultLang) }}</span></div>
        @endforeach
      </div>
    </div>
    <div class="hero-card">
      <div class="browser-bar"><span class="dot"></span><span class="dot"></span><span class="dot"></span></div>
      <div class="mock">
        <div class="mock-top">
          <div class="mock-title">
            <strong data-bn="{{ $txt(data_get($landing, 'hero.dashboard.title'), 'bn') }}" data-en="{{ $txt(data_get($landing, 'hero.dashboard.title'), 'en') }}">{{ $txt(data_get($landing, 'hero.dashboard.title'), $defaultLang) }}</strong>
            <span data-bn="{{ $txt(data_get($landing, 'hero.dashboard.subtitle'), 'bn') }}" data-en="{{ $txt(data_get($landing, 'hero.dashboard.subtitle'), 'en') }}">{{ $txt(data_get($landing, 'hero.dashboard.subtitle'), $defaultLang) }}</span>
          </div>
          <span class="mock-chip" data-bn="{{ $txt(data_get($landing, 'hero.dashboard.chip'), 'bn') }}" data-en="{{ $txt(data_get($landing, 'hero.dashboard.chip'), 'en') }}">{{ $txt(data_get($landing, 'hero.dashboard.chip'), $defaultLang) }}</span>
        </div>
        <div class="mock-stats">
          @foreach(data_get($landing, 'hero.dashboard.stats', []) as $stat)
            <div class="mock-stat"><span data-bn="{{ $txt(data_get($stat, 'label'), 'bn') }}" data-en="{{ $txt(data_get($stat, 'label'), 'en') }}">{{ $txt(data_get($stat, 'label'), $defaultLang) }}</span><strong>{{ data_get($stat, 'value') }}</strong></div>
          @endforeach
        </div>
        <div class="mock-table">
          <div class="mock-row head"><span data-bn="লেনদেন" data-en="Transaction">{{ $defaultLang === 'bn' ? 'লেনদেন' : 'Transaction' }}</span><span data-bn="ডেবিট" data-en="Debit">{{ $defaultLang === 'bn' ? 'ডেবিট' : 'Debit' }}</span><span data-bn="ক্রেডিট" data-en="Credit">{{ $defaultLang === 'bn' ? 'ক্রেডিট' : 'Credit' }}</span></div>
          @foreach(data_get($landing, 'hero.dashboard.rows', []) as $row)
            <div class="mock-row"><span data-bn="{{ $txt(data_get($row, 'name'), 'bn') }}" data-en="{{ $txt(data_get($row, 'name'), 'en') }}">{{ $txt(data_get($row, 'name'), $defaultLang) }}</span><span class="green">{{ data_get($row, 'debit') }}</span><span class="red">{{ data_get($row, 'credit') }}</span></div>
          @endforeach
        </div>
      </div>
    </div>
  </div>
</section>
