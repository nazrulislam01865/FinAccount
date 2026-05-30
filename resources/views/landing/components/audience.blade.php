<section class="section" id="for">
  <div class="container">
    <div class="for-grid">
      <div class="simple-card">
        <div class="icon">{{ data_get($landing, 'audience.icon', '🏪') }}</div>
        <h3 data-bn="{{ $txt(data_get($landing, 'audience.title'), 'bn') }}" data-en="{{ $txt(data_get($landing, 'audience.title'), 'en') }}">{{ $txt(data_get($landing, 'audience.title'), $defaultLang) }}</h3>
        <p data-bn="{{ $txt(data_get($landing, 'audience.body'), 'bn') }}" data-en="{{ $txt(data_get($landing, 'audience.body'), 'en') }}">{{ $txt(data_get($landing, 'audience.body'), $defaultLang) }}</p>
      </div>
      <div class="audience-list">
        @foreach(data_get($landing, 'audiences', []) as $audience)
          <div class="audience">
            <span class="tick">✓</span>
            <div>
              <b data-bn="{{ $txt(data_get($audience, 'title'), 'bn') }}" data-en="{{ $txt(data_get($audience, 'title'), 'en') }}">{{ $txt(data_get($audience, 'title'), $defaultLang) }}</b>
              <span data-bn="{{ $txt(data_get($audience, 'body'), 'bn') }}" data-en="{{ $txt(data_get($audience, 'body'), 'en') }}">{{ $txt(data_get($audience, 'body'), $defaultLang) }}</span>
            </div>
          </div>
        @endforeach
      </div>
    </div>
  </div>
</section>
