<header class="topbar">
  <div class="container nav">
    <a href="#top" class="brand">
      <div class="logo">{{ data_get($landing, 'brand.logo_text', 'হি') }}</div>
      <div>
        <strong>{{ data_get($landing, 'brand.name', 'HisebGhor') }}</strong>
        <span data-bn="{{ $txt(data_get($landing, 'brand.tagline'), 'bn') }}" data-en="{{ $txt(data_get($landing, 'brand.tagline'), 'en') }}">{{ $txt(data_get($landing, 'brand.tagline'), $defaultLang) }}</span>
      </div>
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
      <a class="btn btn-outline" href="{{ auth()->check() ? route('dashboard') : data_get($landing, 'cta.secondary.href', '/login') }}" data-bn="{{ auth()->check() ? 'ড্যাশবোর্ড' : $txt(data_get($landing, 'cta.secondary.label'), 'bn') }}" data-en="{{ auth()->check() ? 'Dashboard' : $txt(data_get($landing, 'cta.secondary.label'), 'en') }}">{{ auth()->check() ? ($defaultLang === 'bn' ? 'ড্যাশবোর্ড' : 'Dashboard') : $txt(data_get($landing, 'cta.secondary.label'), $defaultLang) }}</a>
      <a class="btn btn-primary" href="{{ data_get($landing, 'cta.primary.href', '#contact') }}" data-bn="{{ $txt(data_get($landing, 'cta.primary.label'), 'bn') }}" data-en="{{ $txt(data_get($landing, 'cta.primary.label'), 'en') }}">{{ $txt(data_get($landing, 'cta.primary.label'), $defaultLang) }}</a>
    </div>
  </div>
</header>
