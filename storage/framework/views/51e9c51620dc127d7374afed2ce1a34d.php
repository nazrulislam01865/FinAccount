<section class="section screens" id="features">
  <div class="container">
    <?php echo $__env->make('landing.components.section-title', ['mini' => data_get($landing, 'features.mini'), 'title' => data_get($landing, 'features.title'), 'subtitle' => data_get($landing, 'features.subtitle')], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
    <div class="screen-grid">
      <?php $__currentLoopData = data_get($landing, 'screens', []); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $screen): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
        <div class="screen-card">
          <div class="screen-img">
            <div class="screen-head"><span class="dot"></span><span class="dot"></span><span class="dot"></span></div>
            <div class="screen-body">
              <?php $__currentLoopData = data_get($screen, 'badges', []); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $badge): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                <span class="highlight" data-bn="<?php echo e($txt($badge, 'bn')); ?>" data-en="<?php echo e($txt($badge, 'en')); ?>"><?php echo e($txt($badge, $defaultLang)); ?></span>
              <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
              <div class="screen-lines"><div class="line"></div><div class="line w70"></div><div class="line w45"></div></div>
            </div>
          </div>
          <h3 data-bn="<?php echo e($txt(data_get($screen, 'title'), 'bn')); ?>" data-en="<?php echo e($txt(data_get($screen, 'title'), 'en')); ?>"><?php echo e($txt(data_get($screen, 'title'), $defaultLang)); ?></h3>
          <p data-bn="<?php echo e($txt(data_get($screen, 'body'), 'bn')); ?>" data-en="<?php echo e($txt(data_get($screen, 'body'), 'en')); ?>"><?php echo e($txt(data_get($screen, 'body'), $defaultLang)); ?></p>
        </div>
      <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
    </div>
  </div>
</section>
<?php /**PATH /Applications/XAMPP/xamppfiles/htdocs/laravel/project_work/resources/views/landing/components/features.blade.php ENDPATH**/ ?>