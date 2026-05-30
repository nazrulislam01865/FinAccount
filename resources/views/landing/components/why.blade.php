<section class="section" id="why">
  <div class="container">
    @include('landing.components.section-title', ['mini' => data_get($landing, 'why.mini'), 'title' => data_get($landing, 'why.title'), 'subtitle' => data_get($landing, 'why.subtitle')])
    <div class="grid-3">
      @foreach(data_get($landing, 'why_cards', []) as $card)
        <div class="feature-card">
          <div class="icon">{{ data_get($card, 'icon', '✓') }}</div>
          <h3 data-bn="{{ $txt(data_get($card, 'title'), 'bn') }}" data-en="{{ $txt(data_get($card, 'title'), 'en') }}">{{ $txt(data_get($card, 'title'), $defaultLang) }}</h3>
          <p data-bn="{{ $txt(data_get($card, 'body'), 'bn') }}" data-en="{{ $txt(data_get($card, 'body'), 'en') }}">{{ $txt(data_get($card, 'body'), $defaultLang) }}</p>
        </div>
      @endforeach
    </div>
  </div>
</section>
