<section class="section pricing-section" id="pricing">
  <div class="container">
    @include('landing.components.section-title', [
      'mini' => data_get($landing, 'pricing.mini'),
      'title' => data_get($landing, 'pricing.title'),
      'subtitle' => data_get($landing, 'pricing.subtitle'),
    ])

    <div class="packages implementation-packages">
      @foreach(data_get($landing, 'packages', []) as $package)
        @php
          $isPopular = (bool) data_get($package, 'popular', false);
          $feeRows = [
            ['key' => 'installation', 'icon' => 'wallet'],
            ['key' => 'maintenance', 'icon' => 'settings'],
            ['key' => 'hosting', 'icon' => 'server'],
          ];
        @endphp

        <article class="package implementation-package {{ $isPopular ? 'popular' : '' }}">
          @if($isPopular)
            <div class="recommended-ribbon"
                 data-bn="{{ $txt(data_get($package, 'popular_label'), 'bn', '★ Recommended') }}"
                 data-en="{{ $txt(data_get($package, 'popular_label'), 'en', '★ Recommended') }}">
              {{ $txt(data_get($package, 'popular_label'), $defaultLang, '★ Recommended') }}
            </div>
          @endif

          <div class="package-heading-row">
            <div class="package-heading-main">
              <span class="package-icon-badge">
                @include('landing.components.pricing-icon', ['icon' => data_get($package, 'icon', 'cloud')])
              </span>
              <h3 data-bn="{{ $txt(data_get($package, 'name'), 'bn') }}"
                  data-en="{{ $txt(data_get($package, 'name'), 'en') }}">
                {{ $txt(data_get($package, 'name'), $defaultLang) }}
              </h3>
            </div>

            <span class="package-small-tag"
                  data-bn="{{ $txt(data_get($package, 'tag'), 'bn') }}"
                  data-en="{{ $txt(data_get($package, 'tag'), 'en') }}">
              {{ $txt(data_get($package, 'tag'), $defaultLang) }}
            </span>
          </div>

          <p class="package-description"
             data-bn="{{ $txt(data_get($package, 'body'), 'bn') }}"
             data-en="{{ $txt(data_get($package, 'body'), 'en') }}">
            {{ $txt(data_get($package, 'body'), $defaultLang) }}
          </p>

          <div class="package-fees">
            @foreach($feeRows as $feeRow)
              @php($feePath = 'fees.'.$feeRow['key'])
              <div class="package-fee-row">
                <span class="fee-icon-badge">
                  @include('landing.components.pricing-icon', ['icon' => $feeRow['icon']])
                </span>
                <div class="package-fee-copy">
                  <span class="package-fee-label"
                        data-bn="{{ $txt(data_get($package, $feePath.'.label'), 'bn') }}"
                        data-en="{{ $txt(data_get($package, $feePath.'.label'), 'en') }}">
                    {{ $txt(data_get($package, $feePath.'.label'), $defaultLang) }}
                  </span>
                  <strong>{{ data_get($package, $feePath.'.amount') }}</strong>
                  <small data-bn="{{ $txt(data_get($package, $feePath.'.note'), 'bn') }}"
                         data-en="{{ $txt(data_get($package, $feePath.'.note'), 'en') }}">
                    {{ $txt(data_get($package, $feePath.'.note'), $defaultLang) }}
                  </small>
                </div>
              </div>
            @endforeach
          </div>

          <ul class="package-feature-list">
            @foreach(data_get($package, 'features', []) as $feature)
              <li data-bn="{{ $txt($feature, 'bn') }}"
                  data-en="{{ $txt($feature, 'en') }}">
                {{ $txt($feature, $defaultLang) }}
              </li>
            @endforeach
          </ul>
        </article>
      @endforeach
    </div>

    @if(count(data_get($landing, 'pricing_notes', [])))
      <div class="important-notes-heading">
        <span></span>
        <div>
          <span class="important-note-icon">i</span>
          <strong data-bn="{{ $txt(data_get($landing, 'pricing.notes_title'), 'bn', 'Important Notes') }}"
                  data-en="{{ $txt(data_get($landing, 'pricing.notes_title'), 'en', 'Important Notes') }}">
            {{ $txt(data_get($landing, 'pricing.notes_title'), $defaultLang, 'Important Notes') }}
          </strong>
        </div>
        <span></span>
      </div>

      <div class="pricing-notes-grid">
        @foreach(data_get($landing, 'pricing_notes', []) as $note)
          <article class="pricing-note-card">
            <span class="pricing-note-icon">
              @include('landing.components.pricing-icon', ['icon' => data_get($note, 'icon', 'tag')])
            </span>
            <div>
              <h3 data-bn="{{ $txt(data_get($note, 'title'), 'bn') }}"
                  data-en="{{ $txt(data_get($note, 'title'), 'en') }}">
                {{ $txt(data_get($note, 'title'), $defaultLang) }}
              </h3>
              <p data-bn="{{ $txt(data_get($note, 'body'), 'bn') }}"
                 data-en="{{ $txt(data_get($note, 'body'), 'en') }}">
                {{ $txt(data_get($note, 'body'), $defaultLang) }}
              </p>
            </div>
          </article>
        @endforeach
      </div>
    @endif
  </div>
</section>
