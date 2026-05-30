<section class="section contact" id="contact">
  <div class="container">
    <div class="contact-grid">
      <div class="contact-card">
        <h3 data-bn="<?php echo e($txt(data_get($landing, 'contact.title'), 'bn')); ?>" data-en="<?php echo e($txt(data_get($landing, 'contact.title'), 'en')); ?>"><?php echo e($txt(data_get($landing, 'contact.title'), $defaultLang)); ?></h3>
        <p data-bn="<?php echo e($txt(data_get($landing, 'contact.body'), 'bn')); ?>" data-en="<?php echo e($txt(data_get($landing, 'contact.body'), 'en')); ?>"><?php echo e($txt(data_get($landing, 'contact.body'), $defaultLang)); ?></p>
        <div class="contact-methods">
          <?php if(data_get($landing, 'contact.phone')): ?>
            <a class="contact-method" href="tel:<?php echo e(preg_replace('/\s+/', '', data_get($landing, 'contact.phone'))); ?>"><span class="tick">☎</span><div><b><?php echo e(data_get($landing, 'contact.phone')); ?></b><span data-bn="<?php echo e($txt(data_get($landing, 'contact.phone_note'), 'bn')); ?>" data-en="<?php echo e($txt(data_get($landing, 'contact.phone_note'), 'en')); ?>"><?php echo e($txt(data_get($landing, 'contact.phone_note'), $defaultLang)); ?></span></div></a>
          <?php endif; ?>
          <?php if(data_get($landing, 'contact.email')): ?>
            <a class="contact-method" href="mailto:<?php echo e(data_get($landing, 'contact.email')); ?>"><span class="tick">✉</span><div><b><?php echo e(data_get($landing, 'contact.email')); ?></b><span data-bn="<?php echo e($txt(data_get($landing, 'contact.email_note'), 'bn')); ?>" data-en="<?php echo e($txt(data_get($landing, 'contact.email_note'), 'en')); ?>"><?php echo e($txt(data_get($landing, 'contact.email_note'), $defaultLang)); ?></span></div></a>
          <?php endif; ?>
        </div>
      </div>
      <div class="contact-card">
        <div class="form-status" id="landingFormStatus"></div>
        <form class="form" id="demoForm" method="POST" action="<?php echo e(route('landing.inquiries.store')); ?>">
          <?php echo csrf_field(); ?>
          <input name="name" required data-placeholder-bn="<?php echo e($txt(data_get($landing, 'contact.form.name'), 'bn')); ?>" data-placeholder-en="<?php echo e($txt(data_get($landing, 'contact.form.name'), 'en')); ?>" placeholder="<?php echo e($txt(data_get($landing, 'contact.form.name'), $defaultLang)); ?>">
          <input name="business_name" data-placeholder-bn="<?php echo e($txt(data_get($landing, 'contact.form.business_name'), 'bn')); ?>" data-placeholder-en="<?php echo e($txt(data_get($landing, 'contact.form.business_name'), 'en')); ?>" placeholder="<?php echo e($txt(data_get($landing, 'contact.form.business_name'), $defaultLang)); ?>">
          <input name="mobile" data-placeholder-bn="<?php echo e($txt(data_get($landing, 'contact.form.mobile'), 'bn')); ?>" data-placeholder-en="<?php echo e($txt(data_get($landing, 'contact.form.mobile'), 'en')); ?>" placeholder="<?php echo e($txt(data_get($landing, 'contact.form.mobile'), $defaultLang)); ?>">
          <input name="email" type="email" data-placeholder-bn="<?php echo e($txt(data_get($landing, 'contact.form.email'), 'bn')); ?>" data-placeholder-en="<?php echo e($txt(data_get($landing, 'contact.form.email'), 'en')); ?>" placeholder="<?php echo e($txt(data_get($landing, 'contact.form.email'), $defaultLang)); ?>">
          <textarea name="message" data-placeholder-bn="<?php echo e($txt(data_get($landing, 'contact.form.message'), 'bn')); ?>" data-placeholder-en="<?php echo e($txt(data_get($landing, 'contact.form.message'), 'en')); ?>" placeholder="<?php echo e($txt(data_get($landing, 'contact.form.message'), $defaultLang)); ?>"></textarea>
          <button class="btn btn-primary" type="submit" data-bn="<?php echo e($txt(data_get($landing, 'contact.form.button'), 'bn')); ?>" data-en="<?php echo e($txt(data_get($landing, 'contact.form.button'), 'en')); ?>"><?php echo e($txt(data_get($landing, 'contact.form.button'), $defaultLang)); ?></button>
        </form>
      </div>
    </div>
  </div>
</section>
<?php /**PATH /Applications/XAMPP/xamppfiles/htdocs/laravel/project_work/resources/views/landing/components/contact.blade.php ENDPATH**/ ?>