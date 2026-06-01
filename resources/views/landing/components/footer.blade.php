<footer class="footer">
  <div class="container footer-grid">
    <div>
      <div class="brand brand-image-only footer-brand-image-only" aria-label="{{ data_get($landing, 'brand.name', 'HisebGhor') }}">
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
          <img class="brand-logo-full footer-brand-logo-full" src="{{ $landingImageUrl($brandLogoPath) }}" alt="{{ $brandLogoName ?: data_get($landing, 'brand.name', 'HisebGhor') }}">
        @else
          <div class="brand-fallback-mark">{{ data_get($landing, 'brand.logo_text', 'হি') }}</div>
          <div class="brand-fallback-text"><strong>{{ data_get($landing, 'brand.name', 'HisebGhor') }}</strong><span data-bn="{{ $txt(data_get($landing, 'brand.tagline'), 'bn') }}" data-en="{{ $txt(data_get($landing, 'brand.tagline'), 'en') }}">{{ $txt(data_get($landing, 'brand.tagline'), $defaultLang) }}</span></div>
        @endif
      </div>
      <p data-bn="{{ $txt(data_get($landing, 'footer.text'), 'bn') }}" data-en="{{ $txt(data_get($landing, 'footer.text'), 'en') }}">{{ $txt(data_get($landing, 'footer.text'), $defaultLang) }}</p>
    </div>
    <div class="footer-links">
      @foreach(data_get($landing, 'nav_links', []) as $link)
        <a href="{{ data_get($link, 'href', '#') }}" data-bn="{{ $txt(data_get($link, 'label'), 'bn') }}" data-en="{{ $txt(data_get($link, 'label'), 'en') }}">{{ $txt(data_get($link, 'label'), $defaultLang) }}</a>
      @endforeach
      <a href="#contact" data-bn="যোগাযোগ" data-en="Contact">{{ $defaultLang === 'bn' ? 'যোগাযোগ' : 'Contact' }}</a>
    </div>
  </div>
</footer>
