<section class="section audience-section" id="for">
  <div class="container">
    <div class="audience-heading">
      <div class="audience-heading-icon" aria-hidden="true">{{ data_get($landing, 'audience.icon', '🏪') }}</div>
      <div>
        <h2 data-bn="{{ $txt(data_get($landing, 'audience.title'), 'bn') }}" data-en="{{ $txt(data_get($landing, 'audience.title'), 'en') }}">{{ $txt(data_get($landing, 'audience.title'), $defaultLang) }}</h2>
        <p data-bn="{{ $txt(data_get($landing, 'audience.body'), 'bn') }}" data-en="{{ $txt(data_get($landing, 'audience.body'), 'en') }}">{{ $txt(data_get($landing, 'audience.body'), $defaultLang) }}</p>
      </div>
    </div>

    <div class="audience-list">
      @foreach(data_get($landing, 'audiences', []) as $audience)
        <article class="audience">
          <span class="audience-tick" aria-hidden="true">✓</span>
          <div class="audience-copy">
            <h3 data-bn="{{ $txt(data_get($audience, 'title'), 'bn') }}" data-en="{{ $txt(data_get($audience, 'title'), 'en') }}">{{ $txt(data_get($audience, 'title'), $defaultLang) }}</h3>
            <p data-bn="{{ $txt(data_get($audience, 'body'), 'bn') }}" data-en="{{ $txt(data_get($audience, 'body'), 'en') }}">{{ $txt(data_get($audience, 'body'), $defaultLang) }}</p>
          </div>
        </article>
      @endforeach
    </div>
  </div>
</section>
