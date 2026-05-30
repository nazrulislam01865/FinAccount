<section class="section" id="pricing">
  <div class="container">
    @include('landing.components.section-title', ['mini' => data_get($landing, 'pricing.mini'), 'title' => data_get($landing, 'pricing.title'), 'subtitle' => data_get($landing, 'pricing.subtitle')])
    <div class="packages">
      @foreach(data_get($landing, 'packages', []) as $package)
        <div class="package {{ data_get($package, 'popular') ? 'popular' : '' }}">
          @if(data_get($package, 'popular'))
            <div class="popular-tag" data-bn="{{ $txt(data_get($package, 'tag'), 'bn') }}" data-en="{{ $txt(data_get($package, 'tag'), 'en') }}">{{ $txt(data_get($package, 'tag'), $defaultLang) }}</div>
          @endif
          <h3 data-bn="{{ $txt(data_get($package, 'name'), 'bn') }}" data-en="{{ $txt(data_get($package, 'name'), 'en') }}">{{ $txt(data_get($package, 'name'), $defaultLang) }}</h3>
          <p data-bn="{{ $txt(data_get($package, 'body'), 'bn') }}" data-en="{{ $txt(data_get($package, 'body'), 'en') }}">{{ $txt(data_get($package, 'body'), $defaultLang) }}</p>
          <div class="price">{{ data_get($package, 'price') }} <small data-bn="{{ $txt(data_get($package, 'suffix'), 'bn') }}" data-en="{{ $txt(data_get($package, 'suffix'), 'en') }}">{{ $txt(data_get($package, 'suffix'), $defaultLang) }}</small></div>
          <ul>
            @foreach(data_get($package, 'features', []) as $feature)
              <li data-bn="{{ $txt($feature, 'bn') }}" data-en="{{ $txt($feature, 'en') }}">{{ $txt($feature, $defaultLang) }}</li>
            @endforeach
          </ul>
          <a href="{{ data_get($package, 'button.href', '#contact') }}" class="{{ $buttonClass(data_get($package, 'button.style')) }}" data-bn="{{ $txt(data_get($package, 'button.label'), 'bn') }}" data-en="{{ $txt(data_get($package, 'button.label'), 'en') }}">{{ $txt(data_get($package, 'button.label'), $defaultLang) }}</a>
        </div>
      @endforeach
    </div>
    <div style="margin-top:22px;display:grid;grid-template-columns:repeat(auto-fit,minmax(280px,1fr));gap:18px">
      @foreach(data_get($landing, 'pricing_notes', []) as $note)
        <div class="simple-card">
          <h3 data-bn="{{ $txt(data_get($note, 'title'), 'bn') }}" data-en="{{ $txt(data_get($note, 'title'), 'en') }}">{{ $txt(data_get($note, 'title'), $defaultLang) }}</h3>
          <p data-bn="{{ $txt(data_get($note, 'body'), 'bn') }}" data-en="{{ $txt(data_get($note, 'body'), 'en') }}">{{ $txt(data_get($note, 'body'), $defaultLang) }}</p>
          @if(data_get($note, 'button.label'))
            <a href="{{ data_get($note, 'button.href', '#contact') }}" class="btn btn-dark" style="margin-top:18px" data-bn="{{ $txt(data_get($note, 'button.label'), 'bn') }}" data-en="{{ $txt(data_get($note, 'button.label'), 'en') }}">{{ $txt(data_get($note, 'button.label'), $defaultLang) }}</a>
          @endif
        </div>
      @endforeach
    </div>
  </div>
</section>
