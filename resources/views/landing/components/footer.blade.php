<footer class="footer">
  <div class="container footer-grid">
    <div>
      <div class="brand">
        <div class="logo">{{ data_get($landing, 'brand.logo_text', 'হি') }}</div>
        <div><strong>{{ data_get($landing, 'brand.name', 'HisebGhor') }}</strong><span data-bn="{{ $txt(data_get($landing, 'brand.tagline'), 'bn') }}" data-en="{{ $txt(data_get($landing, 'brand.tagline'), 'en') }}">{{ $txt(data_get($landing, 'brand.tagline'), $defaultLang) }}</span></div>
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
