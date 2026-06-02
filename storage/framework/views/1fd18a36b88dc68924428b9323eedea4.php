<?php
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
?>
<!DOCTYPE html>
<html lang="<?php echo e($defaultLang); ?>">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <meta name="csrf-token" content="<?php echo e(csrf_token()); ?>">
  <meta name="description" content="<?php echo e(data_get($landing, 'meta.description', 'HisebGhor')); ?>">
  <title><?php echo e(data_get($landing, 'meta.title', 'HisebGhor')); ?></title>
  <?php echo $__env->make('landing.components.styles', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
</head>
<body>
  <?php if($isPreview): ?>
      <div class="preview-banner">Preview mode — unpublished changes are visible only to landing page managers.</div>
  <?php endif; ?>

  <?php echo $__env->make('landing.components.nav', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>

  <main id="top">
    <?php if($isEnabled('hero')): ?>
        <?php echo $__env->make('landing.components.hero', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
    <?php endif; ?>

    <?php if($isEnabled('why')): ?>
        <?php echo $__env->make('landing.components.why', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
    <?php endif; ?>

    <?php if($isEnabled('features')): ?>
        <?php echo $__env->make('landing.components.features', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
    <?php endif; ?>

    <?php if($isEnabled('audience')): ?>
        <?php echo $__env->make('landing.components.audience', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
    <?php endif; ?>

    <?php if($isEnabled('pricing')): ?>
        <?php echo $__env->make('landing.components.pricing', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
    <?php endif; ?>

    <?php if($isEnabled('testimonials_section')): ?>
        <?php echo $__env->make('landing.components.testimonials', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
    <?php endif; ?>

    <?php if($isEnabled('faq_section')): ?>
        <?php echo $__env->make('landing.components.faq', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
    <?php endif; ?>

    <?php if($isEnabled('contact')): ?>
        <?php echo $__env->make('landing.components.contact', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
    <?php endif; ?>
  </main>

  <?php echo $__env->make('landing.components.footer', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
  <?php echo $__env->make('landing.components.scripts', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
</body>
</html>
<?php /**PATH /Applications/XAMPP/xamppfiles/htdocs/laravel/project_work/resources/views/landing/show.blade.php ENDPATH**/ ?>