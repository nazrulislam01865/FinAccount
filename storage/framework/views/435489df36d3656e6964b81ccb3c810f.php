<footer class="footer">
  <div class="container footer-grid">
    <div>
      <div class="brand">
        <div class="logo"><?php echo e(data_get($landing, 'brand.logo_text', 'হি')); ?></div>
        <div><strong><?php echo e(data_get($landing, 'brand.name', 'HisebGhor')); ?></strong><span data-bn="<?php echo e($txt(data_get($landing, 'brand.tagline'), 'bn')); ?>" data-en="<?php echo e($txt(data_get($landing, 'brand.tagline'), 'en')); ?>"><?php echo e($txt(data_get($landing, 'brand.tagline'), $defaultLang)); ?></span></div>
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