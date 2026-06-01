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
    @php
      $dashboardImagePath = trim((string) data_get($landing, 'hero.dashboard.image.path', ''));
      $dashboardImageName = trim((string) data_get($landing, 'hero.dashboard.image.name', ''));
    @endphp
    <div class="hero-card hero-image-only-card">
      <div class="dashboard-preview">
        @if($dashboardImagePath !== '')
          <img src="{{ $landingImageUrl($dashboardImagePath) }}" alt="{{ $dashboardImageName ?: 'Dashboard Preview' }}" class="dashboard-preview-image dashboard-preview-image-only">
        @else
          <div class="dashboard-preview-placeholder">
            <span data-bn="ড্যাশবোর্ড প্রিভিউ ছবি" data-en="Dashboard preview image">{{ $defaultLang === 'bn' ? 'ড্যাশবোর্ড প্রিভিউ ছবি' : 'Dashboard preview image' }}</span>
            <small data-bn="শিগগিরই স্ক্রিনশট যুক্ত হবে" data-en="Screenshot will appear here">{{ $defaultLang === 'bn' ? 'শিগগিরই স্ক্রিনশট যুক্ত হবে' : 'Screenshot will appear here' }}</small>
          </div>
        @endif
      </div>
    </div>
  </div>
</section>
