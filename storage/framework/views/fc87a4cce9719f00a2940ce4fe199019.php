<section class="section" id="for">
  <div class="container">
    <div class="for-grid">
      <div class="simple-card">
        <div class="icon"><?php echo e(data_get($landing, 'audience.icon', '🏪')); ?></div>
        <h3 data-bn="<?php echo e($txt(data_get($landing, 'audience.title'), 'bn')); ?>" data-en="<?php echo e($txt(data_get($landing, 'audience.title'), 'en')); ?>"><?php echo e($txt(data_get($landing, 'audience.title'), $defaultLang)); ?></h3>
        <p data-bn="<?php echo e($txt(data_get($landing, 'audience.body'), 'bn')); ?>" data-en="<?php echo e($txt(data_get($landing, 'audience.body'), 'en')); ?>"><?php echo e($txt(data_get($landing, 'audience.body'), $defaultLang)); ?></p>
      </div>
      <div class="audience-list">
        <?php $__currentLoopData = data_get($landing, 'audiences', []); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $audience): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
          <div class="audience">
            <span class="tick">✓</span>
            <div>
              <b data-bn="<?php echo e($txt(data_get($audience, 'title'), 'bn')); ?>" data-en="<?php echo e($txt(data_get($audience, 'title'), 'en')); ?>"><?php echo e($txt(data_get($audience, 'title'), $defaultLang)); ?></b>
              <span data-bn="<?php echo e($txt(data_get($audience, 'body'), 'bn')); ?>" data-en="<?php echo e($txt(data_get($audience, 'body'), 'en')); ?>"><?php echo e($txt(data_get($audience, 'body'), $defaultLang)); ?></span>
            </div>
          </div>
        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
      </div>
    </div>
  </div>
</section>
<?php /**PATH /Applications/XAMPP/xamppfiles/htdocs/laravel/project_work/resources/views/landing/components/audience.blade.php ENDPATH**/ ?>