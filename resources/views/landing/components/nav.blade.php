<header class="topbar">
  <div class="container nav">
    <a href="#top" class="brand brand-image-only" aria-label="{{ data_get($landing, 'brand.name', 'HisebGhor') }}">
      @php
        $brandLogoPath = trim((string) (
          data_get($landing, 'brand.logo.path')
          ?: data_get($landing, 'brand.logo.image.path')
          ?: data_get($landing, 'brand.logo.image_path')
          ?: ''
        ));
        $brandLogoName = trim((string) (
          data_get($landing, 'brand.logo.name')
          ?: data_get($landing, 'brand.logo.image.name')
          ?: data_get($landing, 'brand.logo.image_name')
          ?: ($brandLogoPath !== '' ? basename($brandLogoPath) : '')
        ));
      @endphp
      @if($brandLogoPath !== '')
        <img class="brand-logo-full" src="{{ $landingImageUrl($brandLogoPath) }}" alt="{{ $brandLogoName ?: data_get($landing, 'brand.name', 'HisebGhor') }}">
      @else
        <div class="brand-fallback-mark">{{ data_get($landing, 'brand.logo_text', 'হি') }}</div>
        <div class="brand-fallback-text">
          <strong>{{ data_get($landing, 'brand.name', 'HisebGhor') }}</strong>
          <span data-bn="{{ $txt(data_get($landing, 'brand.tagline'), 'bn') }}" data-en="{{ $txt(data_get($landing, 'brand.tagline'), 'en') }}">{{ $txt(data_get($landing, 'brand.tagline'), $defaultLang) }}</span>
        </div>
      @endif
    </a>
    <nav class="navlinks">
      @foreach(data_get($landing, 'nav_links', []) as $link)
        <a href="{{ data_get($link, 'href', '#') }}" data-bn="{{ $txt(data_get($link, 'label'), 'bn') }}" data-en="{{ $txt(data_get($link, 'label'), 'en') }}">{{ $txt(data_get($link, 'label'), $defaultLang) }}</a>
      @endforeach
    </nav>
    <div class="actions">
      <div class="lang-toggle">
        <button id="bnBtn" class="{{ $defaultLang === 'bn' ? 'active' : '' }}" type="button">বাংলা</button>
        <button id="enBtn" class="{{ $defaultLang === 'en' ? 'active' : '' }}" type="button">EN</button>
      </div>
      <a class="btn btn-primary" href="{{ $landingHref(data_get($landing, 'cta.primary.href', '#contact'), data_get($landing, 'cta.primary.label')) }}" data-bn="{{ $txt(data_get($landing, 'cta.primary.label'), 'bn') }}" data-en="{{ $txt(data_get($landing, 'cta.primary.label'), 'en') }}">{{ $txt(data_get($landing, 'cta.primary.label'), $defaultLang) }}</a>
      <button class="mobile-menu-toggle" type="button" id="landingMenuToggle" aria-controls="landingMobileMenu" aria-expanded="false" aria-label="Open landing page menu">
        <span></span><span></span><span></span>
      </button>
    </div>
  </div>

  <div class="mobile-nav-panel" id="landingMobileMenu" aria-label="Mobile landing page menu">
    <div class="container mobile-nav-inner">
      @foreach(data_get($landing, 'nav_links', []) as $link)
        <a href="{{ data_get($link, 'href', '#') }}" data-bn="{{ $txt(data_get($link, 'label'), 'bn') }}" data-en="{{ $txt(data_get($link, 'label'), 'en') }}">{{ $txt(data_get($link, 'label'), $defaultLang) }}</a>
      @endforeach
      <div class="mobile-nav-actions">
        <a class="btn btn-primary" href="{{ $landingHref(data_get($landing, 'cta.primary.href', '#contact'), data_get($landing, 'cta.primary.label')) }}" data-bn="{{ $txt(data_get($landing, 'cta.primary.label'), 'bn') }}" data-en="{{ $txt(data_get($landing, 'cta.primary.label'), 'en') }}">{{ $txt(data_get($landing, 'cta.primary.label'), $defaultLang) }}</a>
      </div>
    </div>
  </div>
</header>
