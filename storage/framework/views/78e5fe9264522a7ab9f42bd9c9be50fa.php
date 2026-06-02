<section class="hero">
  <div class="container hero-grid">
    <div>
      <div class="eyebrow" data-bn="<?php echo e($txt(data_get($landing, 'hero.eyebrow'), 'bn')); ?>" data-en="<?php echo e($txt(data_get($landing, 'hero.eyebrow'), 'en')); ?>"><?php echo e($txt(data_get($landing, 'hero.eyebrow'), $defaultLang)); ?></div>
      <h1 data-bn="<?php echo e($txt(data_get($landing, 'hero.title'), 'bn')); ?>" data-en="<?php echo e($txt(data_get($landing, 'hero.title'), 'en')); ?>"><?php echo e($txt(data_get($landing, 'hero.title'), $defaultLang)); ?></h1>
      <p data-bn="<?php echo e($txt(data_get($landing, 'hero.subtitle'), 'bn')); ?>" data-en="<?php echo e($txt(data_get($landing, 'hero.subtitle'), 'en')); ?>"><?php echo e($txt(data_get($landing, 'hero.subtitle'), $defaultLang)); ?></p>
      <div class="hero-buttons">
        <?php $__currentLoopData = data_get($landing, 'hero.buttons', []); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $button): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
          <a class="<?php echo e($buttonClass(data_get($button, 'style'))); ?>" href="<?php echo e($landingHref(data_get($button, 'href', '#contact'), data_get($button, 'label'))); ?>" data-bn="<?php echo e($txt(data_get($button, 'label'), 'bn')); ?>" data-en="<?php echo e($txt(data_get($button, 'label'), 'en')); ?>"><?php echo e($txt(data_get($button, 'label'), $defaultLang)); ?></a>
        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
      </div>
      <div class="trust-row">
        <?php $__currentLoopData = data_get($landing, 'trust_items', []); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $item): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
          <div class="trust"><span class="tick">✓</span><span data-bn="<?php echo e($txt($item, 'bn')); ?>" data-en="<?php echo e($txt($item, 'en')); ?>"><?php echo e($txt($item, $defaultLang)); ?></span></div>
        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
      </div>
    </div>
    <?php
      $dashboardImagePath = trim((string) data_get($landing, 'hero.dashboard.image.path', ''));
      $dashboardImageName = trim((string) data_get($landing, 'hero.dashboard.image.name', ''));
    ?>
    <div class="hero-card hero-image-only-card">
      <div class="dashboard-preview">
        <?php if($dashboardImagePath !== ''): ?>
          <img src="<?php echo e($landingImageUrl($dashboardImagePath)); ?>" alt="<?php echo e($dashboardImageName ?: 'Dashboard Preview'); ?>" class="dashboard-preview-image dashboard-preview-image-only">
        <?php else: ?>
          <div class="dashboard-preview-placeholder">
            <span data-bn="ড্যাশবোর্ড প্রিভিউ ছবি" data-en="Dashboard preview image"><?php echo e($defaultLang === 'bn' ? 'ড্যাশবোর্ড প্রিভিউ ছবি' : 'Dashboard preview image'); ?></span>
            <small data-bn="শিগগিরই স্ক্রিনশট যুক্ত হবে" data-en="Screenshot will appear here"><?php echo e($defaultLang === 'bn' ? 'শিগগিরই স্ক্রিনশট যুক্ত হবে' : 'Screenshot will appear here'); ?></small>
          </div>
        <?php endif; ?>
      </div>
    </div>
  </div>
</section>
<?php /**PATH /Applications/XAMPP/xamppfiles/htdocs/laravel/project_work/resources/views/landing/components/hero.blade.php ENDPATH**/ ?>