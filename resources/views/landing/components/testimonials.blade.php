<section class="section">
  <div class="container">
    @include('landing.components.section-title', ['mini' => data_get($landing, 'testimonials_section.mini'), 'title' => data_get($landing, 'testimonials_section.title'), 'subtitle' => null])
    <div class="testimonials">
      @foreach(data_get($landing, 'testimonials', []) as $testimonial)
        <div class="testimonial">
          <div class="quote">“</div>
          <p data-bn="{{ $txt(data_get($testimonial, 'quote'), 'bn') }}" data-en="{{ $txt(data_get($testimonial, 'quote'), 'en') }}">{{ $txt(data_get($testimonial, 'quote'), $defaultLang) }}</p>
          <div class="person">
            <div class="person-avatar">{{ data_get($testimonial, 'avatar', 'H') }}</div>
            <div><strong>{{ data_get($testimonial, 'name') }}</strong><span data-bn="{{ $txt(data_get($testimonial, 'role'), 'bn') }}" data-en="{{ $txt(data_get($testimonial, 'role'), 'en') }}">{{ $txt(data_get($testimonial, 'role'), $defaultLang) }}</span></div>
          </div>
        </div>
      @endforeach
    </div>
  </div>
</section>
