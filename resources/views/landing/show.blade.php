@php
    $txt = function ($value, string $lang = 'bn', string $fallback = '') {
        if (is_array($value)) {
            return (string) ($value[$lang] ?? $value['bn'] ?? $value['en'] ?? $fallback);
        }

        return (string) ($value ?? $fallback);
    };

    $buttonClass = fn ($style = null) => match ($style) {
        'outline' => 'btn btn-outline',
        'dark' => 'btn btn-dark',
        default => 'btn btn-primary',
    };

    $isEnabled = fn (string $key) => (bool) data_get($landing, $key.'.enabled', true);
    $defaultLang = data_get($landing, 'meta.default_lang', 'bn') === 'en' ? 'en' : 'bn';
@endphp
<!DOCTYPE html>
<html lang="{{ $defaultLang }}">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <meta name="csrf-token" content="{{ csrf_token() }}">
  <meta name="description" content="{{ data_get($landing, 'meta.description', 'HisebGhor') }}">
  <title>{{ data_get($landing, 'meta.title', 'HisebGhor') }}</title>
  @include('landing.components.styles')
</head>
<body>
  @if($isPreview)
      <div class="preview-banner">Preview mode — unpublished changes are visible only to landing page managers.</div>
  @endif

  @include('landing.components.nav')

  <main id="top">
    @if($isEnabled('hero'))
        @include('landing.components.hero')
    @endif

    @if($isEnabled('why'))
        @include('landing.components.why')
    @endif

    @if($isEnabled('features'))
        @include('landing.components.features')
    @endif

    @if($isEnabled('audience'))
        @include('landing.components.audience')
    @endif

    @if($isEnabled('pricing'))
        @include('landing.components.pricing')
    @endif

    @if($isEnabled('testimonials_section'))
        @include('landing.components.testimonials')
    @endif

    @if($isEnabled('faq_section'))
        @include('landing.components.faq')
    @endif

    @if($isEnabled('contact'))
        @include('landing.components.contact')
    @endif
  </main>

  @include('landing.components.footer')
  @include('landing.components.scripts')
</body>
</html>
