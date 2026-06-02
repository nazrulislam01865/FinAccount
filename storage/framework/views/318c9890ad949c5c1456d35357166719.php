<section class="section" id="pricing">
  <div class="container">
    <?php echo $__env->make('landing.components.section-title', ['mini' => data_get($landing, 'pricing.mini'), 'title' => data_get($landing, 'pricing.title'), 'subtitle' => data_get($landing, 'pricing.subtitle')], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
    <div class="packages">
      <?php $__currentLoopData = data_get($landing, 'packages', []); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $package): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
        <div class="package <?php echo e(data_get($package, 'popular') ? 'popular' : ''); ?>">
          <?php if(data_get($package, 'popular')): ?>
            <div class="popular-tag" data-bn="<?php echo e($txt(data_get($package, 'tag'), 'bn')); ?>" data-en="<?php echo e($txt(data_get($package, 'tag'), 'en')); ?>"><?php echo e($txt(data_get($package, 'tag'), $defaultLang)); ?></div>
          <?php endif; ?>
          <h3 data-bn="<?php echo e($txt(data_get($package, 'name'), 'bn')); ?>" data-en="<?php echo e($txt(data_get($package, 'name'), 'en')); ?>"><?php echo e($txt(data_get($package, 'name'), $defaultLang)); ?></h3>
          <p data-bn="<?php echo e($txt(data_get($package, 'body'), 'bn')); ?>" data-en="<?php echo e($txt(data_get($package, 'body'), 'en')); ?>"><?php echo e($txt(data_get($package, 'body'), $defaultLang)); ?></p>
          <div class="price"><?php echo e(data_get($package, 'price')); ?> <small data-bn="<?php echo e($txt(data_get($package, 'suffix'), 'bn')); ?>" data-en="<?php echo e($txt(data_get($package, 'suffix'), 'en')); ?>"><?php echo e($txt(data_get($package, 'suffix'), $defaultLang)); ?></small></div>
          <ul>
            <?php $__currentLoopData = data_get($package, 'features', []); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $feature): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
              <li data-bn="<?php echo e($txt($feature, 'bn')); ?>" data-en="<?php echo e($txt($feature, 'en')); ?>"><?php echo e($txt($feature, $defaultLang)); ?></li>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
          </ul>
          <a href="<?php echo e($landingHref(data_get($package, 'button.href', '#contact'), data_get($package, 'button.label'))); ?>" class="<?php echo e($buttonClass(data_get($package, 'button.style'))); ?>" data-bn="<?php echo e($txt(data_get($package, 'button.label'), 'bn')); ?>" data-en="<?php echo e($txt(data_get($package, 'button.label'), 'en')); ?>"><?php echo e($txt(data_get($package, 'button.label'), $defaultLang)); ?></a>
        </div>
      <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
    </div>
    <div style="margin-top:22px;display:grid;grid-template-columns:repeat(auto-fit,minmax(280px,1fr));gap:18px">
      <?php $__currentLoopData = data_get($landing, 'pricing_notes', []); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $note): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
        <div class="simple-card">
          <h3 data-bn="<?php echo e($txt(data_get($note, 'title'), 'bn')); ?>" data-en="<?php echo e($txt(data_get($note, 'title'), 'en')); ?>"><?php echo e($txt(data_get($note, 'title'), $defaultLang)); ?></h3>
          <p data-bn="<?php echo e($txt(data_get($note, 'body'), 'bn')); ?>" data-en="<?php echo e($txt(data_get($note, 'body'), 'en')); ?>"><?php echo e($txt(data_get($note, 'body'), $defaultLang)); ?></p>
          <?php if(data_get($note, 'button.label')): ?>
            <a href="<?php echo e($landingHref(data_get($note, 'button.href', '#contact'), data_get($note, 'button.label'))); ?>" class="btn btn-dark" style="margin-top:18px" data-bn="<?php echo e($txt(data_get($note, 'button.label'), 'bn')); ?>" data-en="<?php echo e($txt(data_get($note, 'button.label'), 'en')); ?>"><?php echo e($txt(data_get($note, 'button.label'), $defaultLang)); ?></a>
          <?php endif; ?>
        </div>
      <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
    </div>
  </div>
</section>
<?php /**PATH /Applications/XAMPP/xamppfiles/htdocs/laravel/project_work/resources/views/landing/components/pricing.blade.php ENDPATH**/ ?>