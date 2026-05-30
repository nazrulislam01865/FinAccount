<section class="section screens" id="features">
  <div class="container">
    @include('landing.components.section-title', ['mini' => data_get($landing, 'features.mini'), 'title' => data_get($landing, 'features.title'), 'subtitle' => data_get($landing, 'features.subtitle')])
    <div class="screen-grid">
      @foreach(data_get($landing, 'screens', []) as $screen)
        <div class="screen-card">
          <div class="screen-img">
            <div class="screen-head"><span class="dot"></span><span class="dot"></span><span class="dot"></span></div>
            <div class="screen-body">
              @foreach(data_get($screen, 'badges', []) as $badge)
                <span class="highlight" data-bn="{{ $txt($badge, 'bn') }}" data-en="{{ $txt($badge, 'en') }}">{{ $txt($badge, $defaultLang) }}</span>
              @endforeach
              <div class="screen-lines"><div class="line"></div><div class="line w70"></div><div class="line w45"></div></div>
            </div>
          </div>
          <h3 data-bn="{{ $txt(data_get($screen, 'title'), 'bn') }}" data-en="{{ $txt(data_get($screen, 'title'), 'en') }}">{{ $txt(data_get($screen, 'title'), $defaultLang) }}</h3>
          <p data-bn="{{ $txt(data_get($screen, 'body'), 'bn') }}" data-en="{{ $txt(data_get($screen, 'body'), 'en') }}">{{ $txt(data_get($screen, 'body'), $defaultLang) }}</p>
        </div>
      @endforeach
    </div>
  </div>
</section>
