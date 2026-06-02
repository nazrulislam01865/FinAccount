<div class="section-title">
  <?php if(!empty($mini)): ?>
    <div class="mini" data-bn="<?php echo e($txt($mini, 'bn')); ?>" data-en="<?php echo e($txt($mini, 'en')); ?>"><?php echo e($txt($mini, $defaultLang)); ?></div>
  <?php endif; ?>
  <h2 data-bn="<?php echo e($txt($title ?? '', 'bn')); ?>" data-en="<?php echo e($txt($title ?? '', 'en')); ?>"><?php echo e($txt($title ?? '', $defaultLang)); ?></h2>
  <?php if(!empty($subtitle)): ?>
    <p data-bn="<?php echo e($txt($subtitle, 'bn')); ?>" data-en="<?php echo e($txt($subtitle, 'en')); ?>"><?php echo e($txt($subtitle, $defaultLang)); ?></p>
  <?php endif; ?>
</div>
<?php /**PATH /Applications/XAMPP/xamppfiles/htdocs/laravel/project_work/resources/views/landing/components/section-title.blade.php ENDPATH**/ ?>