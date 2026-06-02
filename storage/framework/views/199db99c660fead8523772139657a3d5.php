<header class="topbar">
  <div class="container nav">
    <a href="#top" class="brand brand-image-only" aria-label="<?php echo e(data_get($landing, 'brand.name', 'HisebGhor')); ?>">
      <?php
        $brandLogoPath = trim((string) (
          data_get($landing, 'brand.logo.path')
          ?: data_get($landing, 'brand.logo.image.path')
          ?: data_get($landing, 'brand.logo.image_path')
          ?: ''
        ));
        $brandLogoName = trim((string) (
          data_get($landing, 'brand.logo.name')
          ?: data_get($landing, 'brand.logo.image.name')
          ?: data_get($landing, 'brand.logo.image_name')
          ?: ($brandLogoPath !== '' ? basename($brandLogoPath) : '')
        ));
      ?>
      <?php if($brandLogoPath !== ''): ?>
        <img class="brand-logo-full" src="<?php echo e($landingImageUrl($brandLogoPath)); ?>" alt="<?php echo e($brandLogoName ?: data_get($landing, 'brand.name', 'HisebGhor')); ?>">
      <?php else: ?>
        <div class="brand-fallback-mark"><?php echo e(data_get($landing, 'brand.logo_text', 'হি')); ?></div>
        <div class="brand-fallback-text">
          <strong><?php echo e(data_get($landing, 'brand.name', 'HisebGhor')); ?></strong>
          <span data-bn="<?php echo e($txt(data_get($landing, 'brand.tagline'), 'bn')); ?>" data-en="<?php echo e($txt(data_get($landing, 'brand.tagline'), 'en')); ?>"><?php echo e($txt(data_get($landing, 'brand.tagline'), $defaultLang)); ?></span>
        </div>
      <?php endif; ?>
    </a>
    <nav class="navlinks">
      <?php $__currentLoopData = data_get($landing, 'nav_links', []); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $link): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
        <a href="<?php echo e(data_get($link, 'href', '#')); ?>" data-bn="<?php echo e($txt(data_get($link, 'label'), 'bn')); ?>" data-en="<?php echo e($txt(data_get($link, 'label'), 'en')); ?>"><?php echo e($txt(data_get($link, 'label'), $defaultLang)); ?></a>
      <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
    </nav>
    <div class="actions">
      <div class="lang-toggle">
        <button id="bnBtn" class="<?php echo e($defaultLang === 'bn' ? 'active' : ''); ?>" type="button">বাংলা</button>
        <button id="enBtn" class="<?php echo e($defaultLang === 'en' ? 'active' : ''); ?>" type="button">EN</button>
      </div>
      <a class="btn btn-primary" href="<?php echo e($landingHref(data_get($landing, 'cta.primary.href', '#contact'), data_get($landing, 'cta.primary.label'))); ?>" data-bn="<?php echo e($txt(data_get($landing, 'cta.primary.label'), 'bn')); ?>" data-en="<?php echo e($txt(data_get($landing, 'cta.primary.label'), 'en')); ?>"><?php echo e($txt(data_get($landing, 'cta.primary.label'), $defaultLang)); ?></a>
      <button class="mobile-menu-toggle" type="button" id="landingMenuToggle" aria-controls="landingMobileMenu" aria-expanded="false" aria-label="Open landing page menu">
        <span></span><span></span><span></span>
      </button>
    </div>
  </div>

  <div class="mobile-nav-panel" id="landingMobileMenu" aria-label="Mobile landing page menu">
    <div class="container mobile-nav-inner">
      <?php $__currentLoopData = data_get($landing, 'nav_links', []); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $link): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
        <a href="<?php echo e(data_get($link, 'href', '#')); ?>" data-bn="<?php echo e($txt(data_get($link, 'label'), 'bn')); ?>" data-en="<?php echo e($txt(data_get($link, 'label'), 'en')); ?>"><?php echo e($txt(data_get($link, 'label'), $defaultLang)); ?></a>
      <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
      <div class="mobile-nav-actions">
        <a class="btn btn-primary" href="<?php echo e($landingHref(data_get($landing, 'cta.primary.href', '#contact'), data_get($landing, 'cta.primary.label'))); ?>" data-bn="<?php echo e($txt(data_get($landing, 'cta.primary.label'), 'bn')); ?>" data-en="<?php echo e($txt(data_get($landing, 'cta.primary.label'), 'en')); ?>"><?php echo e($txt(data_get($landing, 'cta.primary.label'), $defaultLang)); ?></a>
      </div>
    </div>
  </div>
</header>
<?php /**PATH /Applications/XAMPP/xamppfiles/htdocs/laravel/project_work/resources/views/landing/components/nav.blade.php ENDPATH**/ ?>