<section class="section">
  <div class="container">
    <?php echo $__env->make('landing.components.section-title', ['mini' => data_get($landing, 'testimonials_section.mini'), 'title' => data_get($landing, 'testimonials_section.title'), 'subtitle' => null], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
    <div class="testimonials">
      <?php $__currentLoopData = data_get($landing, 'testimonials', []); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $testimonial): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
        <div class="testimonial">
          <div class="quote">“</div>
          <p data-bn="<?php echo e($txt(data_get($testimonial, 'quote'), 'bn')); ?>" data-en="<?php echo e($txt(data_get($testimonial, 'quote'), 'en')); ?>"><?php echo e($txt(data_get($testimonial, 'quote'), $defaultLang)); ?></p>
          <div class="person">
            <div class="person-avatar"><?php echo e(data_get($testimonial, 'avatar', 'H')); ?></div>
            <div><strong><?php echo e(data_get($testimonial, 'name')); ?></strong><span data-bn="<?php echo e($txt(data_get($testimonial, 'role'), 'bn')); ?>" data-en="<?php echo e($txt(data_get($testimonial, 'role'), 'en')); ?>"><?php echo e($txt(data_get($testimonial, 'role'), $defaultLang)); ?></span></div>
          </div>
        </div>
      <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
    </div>
  </div>
</section>
<?php /**PATH /Applications/XAMPP/xamppfiles/htdocs/laravel/project_work/resources/views/landing/components/testimonials.blade.php ENDPATH**/ ?>