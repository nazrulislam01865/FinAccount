<section class="section" id="faq">
  <div class="container">
    @include('landing.components.section-title', ['mini' => data_get($landing, 'faq_section.mini'), 'title' => data_get($landing, 'faq_section.title'), 'subtitle' => null])
    <div class="faq">
      @foreach(data_get($landing, 'faqs', []) as $faq)
        <div class="faq-item {{ data_get($faq, 'open') ? 'open' : '' }}">
          <div class="faq-q"><span data-bn="{{ $txt(data_get($faq, 'question'), 'bn') }}" data-en="{{ $txt(data_get($faq, 'question'), 'en') }}">{{ $txt(data_get($faq, 'question'), $defaultLang) }}</span><b>+</b></div>
          <div class="faq-a" data-bn="{{ $txt(data_get($faq, 'answer'), 'bn') }}" data-en="{{ $txt(data_get($faq, 'answer'), 'en') }}">{{ $txt(data_get($faq, 'answer'), $defaultLang) }}</div>
        </div>
      @endforeach
    </div>
  </div>
</section>
