<footer class="footer">
  <div class="container footer-grid">
    <div>
      <div class="brand brand-image-only footer-brand-image-only" aria-label="<?php echo e(data_get($landing, 'brand.name', 'HisebGhor')); ?>">
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
          <img class="brand-logo-full footer-brand-logo-full" src="<?php echo e($landingImageUrl($brandLogoPath)); ?>" alt="<?php echo e($brandLogoName ?: data_get($landing, 'brand.name', 'HisebGhor')); ?>">
        <?php else: ?>
          <div class="brand-fallback-mark"><?php echo e(data_get($landing, 'brand.logo_text', 'হি')); ?></div>
          <div class="brand-fallback-text"><strong><?php echo e(data_get($landing, 'brand.name', 'HisebGhor')); ?></strong><span data-bn="<?php echo e($txt(data_get($landing, 'brand.tagline'), 'bn')); ?>" data-en="<?php echo e($txt(data_get($landing, 'brand.tagline'), 'en')); ?>"><?php echo e($txt(data_get($landing, 'brand.tagline'), $defaultLang)); ?></span></div>
        <?php endif; ?>
      </div>
      <p data-bn="<?php echo e($txt(data_get($landing, 'footer.text'), 'bn')); ?>" data-en="<?php echo e($txt(data_get($landing, 'footer.text'), 'en')); ?>"><?php echo e($txt(data_get($landing, 'footer.text'), $defaultLang)); ?></p>
    </div>
    <div class="footer-links">
      <?php $__currentLoopData = data_get($landing, 'nav_links', []); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $link): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
        <a href="<?php echo e(data_get($link, 'href', '#')); ?>" data-bn="<?php echo e($txt(data_get($link, 'label'), 'bn')); ?>" data-en="<?php echo e($txt(data_get($link, 'label'), 'en')); ?>"><?php echo e($txt(data_get($link, 'label'), $defaultLang)); ?></a>
      <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
      <a href="#contact" data-bn="যোগাযোগ" data-en="Contact"><?php echo e($defaultLang === 'bn' ? 'যোগাযোগ' : 'Contact'); ?></a>
    </div>
  </div>
</footer>
<?php /**PATH /Applications/XAMPP/xamppfiles/htdocs/laravel/project_work/resources/views/landing/components/footer.blade.php ENDPATH**/ ?>