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
    $landingHref = function ($href = null, $label = null): string {
        $href = trim((string) ($href ?: '#'));
        return $href !== '' ? $href : '#';
    };
    $landingWhatsAppUrl = function (?string $phone): string {
        $digits = preg_replace('/\D+/', '', (string) $phone);
        if ($digits === '') {
            return '#contact';
        }
        if (str_starts_with($digits, '00')) {
            $digits = substr($digits, 2);
        }
        if (str_starts_with($digits, '0')) {
            $digits = '880'.substr($digits, 1);
        }
        return 'https://wa.me/'.$digits;
    };
    $landingImageUrl = function (?string $path): string {
        $path = trim((string) $path);
        if ($path === '') {
            return '';
        }
        if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
            return $path;
        }

        $relativePath = ltrim($path, '/');
        $url = asset($relativePath);
        $fullPath = public_path($relativePath);

        return is_file($fullPath) ? $url.'?v='.filemtime($fullPath) : $url;
    };
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
