<section class="section screens" id="features">
  <div class="container">
    <?php echo $__env->make('landing.components.section-title', ['mini' => data_get($landing, 'features.mini'), 'title' => data_get($landing, 'features.title'), 'subtitle' => data_get($landing, 'features.subtitle')], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
    <div class="screen-grid">
      <?php $__currentLoopData = data_get($landing, 'screens', []); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $screen): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
        <?php
          $screenImagePath = trim((string) data_get($screen, 'image.path', ''));
          $screenImageName = trim((string) data_get($screen, 'image.name', ''));
        ?>
        <div class="screen-card">
          <div class="screen-img <?php echo e($screenImagePath !== '' ? 'has-uploaded-image' : ''); ?>">
            <?php if($screenImagePath !== ''): ?>
              <img src="<?php echo e($landingImageUrl($screenImagePath)); ?>" alt="<?php echo e($screenImageName ?: $txt(data_get($screen, 'title'), $defaultLang, 'Feature screen preview')); ?>" class="screen-card-image">
            <?php else: ?>
              <div class="screen-image-placeholder">
                <span data-bn="স্ক্রিন প্রিভিউ ছবি" data-en="Screen preview image">
                  <?php echo e($defaultLang === 'bn' ? 'স্ক্রিন প্রিভিউ ছবি' : 'Screen preview image'); ?>

                </span>
                <small data-bn="অ্যাডমিন থেকে ছবি আপলোড করুন" data-en="Upload image from admin">
                  <?php echo e($defaultLang === 'bn' ? 'অ্যাডমিন থেকে ছবি আপলোড করুন' : 'Upload image from admin'); ?>

                </small>
              </div>
            <?php endif; ?>
          </div>
          <h3 data-bn="<?php echo e($txt(data_get($screen, 'title'), 'bn')); ?>" data-en="<?php echo e($txt(data_get($screen, 'title'), 'en')); ?>"><?php echo e($txt(data_get($screen, 'title'), $defaultLang)); ?></h3>
          <p data-bn="<?php echo e($txt(data_get($screen, 'body'), 'bn')); ?>" data-en="<?php echo e($txt(data_get($screen, 'body'), 'en')); ?>"><?php echo e($txt(data_get($screen, 'body'), $defaultLang)); ?></p>
        </div>
      <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
    </div>
  </div>
</section>
<?php /**PATH /Applications/XAMPP/xamppfiles/htdocs/laravel/project_work/resources/views/landing/components/features.blade.php ENDPATH**/ ?>