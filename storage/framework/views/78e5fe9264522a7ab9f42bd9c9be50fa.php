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
    <div class="hero-card">
      <div class="browser-bar"><span class="dot"></span><span class="dot"></span><span class="dot"></span></div>
      <div class="mock">
        <div class="mock-top">
          <div class="mock-title">
            <strong data-bn="<?php echo e($txt(data_get($landing, 'hero.dashboard.title'), 'bn')); ?>" data-en="<?php echo e($txt(data_get($landing, 'hero.dashboard.title'), 'en')); ?>"><?php echo e($txt(data_get($landing, 'hero.dashboard.title'), $defaultLang)); ?></strong>
            <span data-bn="<?php echo e($txt(data_get($landing, 'hero.dashboard.subtitle'), 'bn')); ?>" data-en="<?php echo e($txt(data_get($landing, 'hero.dashboard.subtitle'), 'en')); ?>"><?php echo e($txt(data_get($landing, 'hero.dashboard.subtitle'), $defaultLang)); ?></span>
          </div>
          <span class="mock-chip" data-bn="<?php echo e($txt(data_get($landing, 'hero.dashboard.chip'), 'bn')); ?>" data-en="<?php echo e($txt(data_get($landing, 'hero.dashboard.chip'), 'en')); ?>"><?php echo e($txt(data_get($landing, 'hero.dashboard.chip'), $defaultLang)); ?></span>
        </div>
        <div class="mock-stats">
          <?php $__currentLoopData = data_get($landing, 'hero.dashboard.stats', []); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $stat): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
            <div class="mock-stat"><span data-bn="<?php echo e($txt(data_get($stat, 'label'), 'bn')); ?>" data-en="<?php echo e($txt(data_get($stat, 'label'), 'en')); ?>"><?php echo e($txt(data_get($stat, 'label'), $defaultLang)); ?></span><strong><?php echo e(data_get($stat, 'value')); ?></strong></div>
          <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
        </div>
        <div class="mock-table">
          <div class="mock-row head"><span data-bn="লেনদেন" data-en="Transaction"><?php echo e($defaultLang === 'bn' ? 'লেনদেন' : 'Transaction'); ?></span><span data-bn="ডেবিট" data-en="Debit"><?php echo e($defaultLang === 'bn' ? 'ডেবিট' : 'Debit'); ?></span><span data-bn="ক্রেডিট" data-en="Credit"><?php echo e($defaultLang === 'bn' ? 'ক্রেডিট' : 'Credit'); ?></span></div>
          <?php $__currentLoopData = data_get($landing, 'hero.dashboard.rows', []); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $row): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
            <div class="mock-row"><span data-bn="<?php echo e($txt(data_get($row, 'name'), 'bn')); ?>" data-en="<?php echo e($txt(data_get($row, 'name'), 'en')); ?>"><?php echo e($txt(data_get($row, 'name'), $defaultLang)); ?></span><span class="green"><?php echo e(data_get($row, 'debit')); ?></span><span class="red"><?php echo e(data_get($row, 'credit')); ?></span></div>
          <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
        </div>
      </div>
    </div>
  </div>
</section>
<?php /**PATH /Applications/XAMPP/xamppfiles/htdocs/laravel/project_work/resources/views/landing/components/hero.blade.php ENDPATH**/ ?>