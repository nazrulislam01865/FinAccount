<section class="section screens" id="features">
  <div class="container">
    @include('landing.components.section-title', ['mini' => data_get($landing, 'features.mini'), 'title' => data_get($landing, 'features.title'), 'subtitle' => data_get($landing, 'features.subtitle')])
    <div class="screen-grid">
      @foreach(data_get($landing, 'screens', []) as $screen)
        @php
          $screenImagePath = trim((string) data_get($screen, 'image.path', ''));
          $screenImageName = trim((string) data_get($screen, 'image.name', ''));
        @endphp
        <div class="screen-card">
          <div class="screen-img {{ $screenImagePath !== '' ? 'has-uploaded-image' : '' }}">
            @if($screenImagePath !== '')
              <img src="{{ $landingImageUrl($screenImagePath) }}" alt="{{ $screenImageName ?: $txt(data_get($screen, 'title'), $defaultLang, 'Feature screen preview') }}" class="screen-card-image">
            @else
              <div class="screen-image-placeholder">
                <span data-bn="স্ক্রিন প্রিভিউ ছবি" data-en="Screen preview image">
                  {{ $defaultLang === 'bn' ? 'স্ক্রিন প্রিভিউ ছবি' : 'Screen preview image' }}
                </span>
                <small data-bn="অ্যাডমিন থেকে ছবি আপলোড করুন" data-en="Upload image from admin">
                  {{ $defaultLang === 'bn' ? 'অ্যাডমিন থেকে ছবি আপলোড করুন' : 'Upload image from admin' }}
                </small>
              </div>
            @endif
          </div>
          <h3 data-bn="{{ $txt(data_get($screen, 'title'), 'bn') }}" data-en="{{ $txt(data_get($screen, 'title'), 'en') }}">{{ $txt(data_get($screen, 'title'), $defaultLang) }}</h3>
          <p data-bn="{{ $txt(data_get($screen, 'body'), 'bn') }}" data-en="{{ $txt(data_get($screen, 'body'), 'en') }}">{{ $txt(data_get($screen, 'body'), $defaultLang) }}</p>
        </div>
      @endforeach
    </div>
  </div>
</section>
