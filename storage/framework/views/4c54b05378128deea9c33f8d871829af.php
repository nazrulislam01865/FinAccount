<section class="section" id="why">
  <div class="container">
    <?php echo $__env->make('landing.components.section-title', ['mini' => data_get($landing, 'why.mini'), 'title' => data_get($landing, 'why.title'), 'subtitle' => data_get($landing, 'why.subtitle')], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
    <div class="grid-3">
      <?php $__currentLoopData = data_get($landing, 'why_cards', []); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $card): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
        <div class="feature-card">
          <div class="icon"><?php echo e(data_get($card, 'icon', '✓')); ?></div>
          <h3 data-bn="<?php echo e($txt(data_get($card, 'title'), 'bn')); ?>" data-en="<?php echo e($txt(data_get($card, 'title'), 'en')); ?>"><?php echo e($txt(data_get($card, 'title'), $defaultLang)); ?></h3>
          <p data-bn="<?php echo e($txt(data_get($card, 'body'), 'bn')); ?>" data-en="<?php echo e($txt(data_get($card, 'body'), 'en')); ?>"><?php echo e($txt(data_get($card, 'body'), $defaultLang)); ?></p>
        </div>
      <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
    </div>
  </div>
</section>
<?php /**PATH /Applications/XAMPP/xamppfiles/htdocs/laravel/project_work/resources/views/landing/components/why.blade.php ENDPATH**/ ?>