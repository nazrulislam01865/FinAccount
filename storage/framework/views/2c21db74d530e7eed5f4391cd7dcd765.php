<section class="section" id="faq">
  <div class="container">
    <?php echo $__env->make('landing.components.section-title', ['mini' => data_get($landing, 'faq_section.mini'), 'title' => data_get($landing, 'faq_section.title'), 'subtitle' => null], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
    <div class="faq">
      <?php $__currentLoopData = data_get($landing, 'faqs', []); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $faq): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
        <div class="faq-item <?php echo e(data_get($faq, 'open') ? 'open' : ''); ?>">
          <div class="faq-q"><span data-bn="<?php echo e($txt(data_get($faq, 'question'), 'bn')); ?>" data-en="<?php echo e($txt(data_get($faq, 'question'), 'en')); ?>"><?php echo e($txt(data_get($faq, 'question'), $defaultLang)); ?></span><b>+</b></div>
          <div class="faq-a" data-bn="<?php echo e($txt(data_get($faq, 'answer'), 'bn')); ?>" data-en="<?php echo e($txt(data_get($faq, 'answer'), 'en')); ?>"><?php echo e($txt(data_get($faq, 'answer'), $defaultLang)); ?></div>
        </div>
      <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
    </div>
  </div>
</section>
<?php /**PATH /Applications/XAMPP/xamppfiles/htdocs/laravel/project_work/resources/views/landing/components/faq.blade.php ENDPATH**/ ?>