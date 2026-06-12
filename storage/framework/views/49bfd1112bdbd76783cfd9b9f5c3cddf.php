<!DOCTYPE html>
<html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title>Forgot Password</title><?php echo app('Illuminate\Foundation\Vite')(['resources/css/app.css', 'resources/js/app.js']); ?></head><body>
<div class="auth-shell"><div class="auth-card"><span class="page-label auth-page-label">Forgot Password</span><h2 class="auth-title">Forgot Password</h2><p class="auth-subtitle">Enter your email and we will send a reset link.</p><?php if(session('status')): ?><div class="alert-success"><?php echo e(session('status')); ?></div><?php endif; ?><form method="POST" action="<?php echo e(route('password.email')); ?>" class="auth-form"><?php echo csrf_field(); ?><div><label>Email</label><input type="email" name="email" value="<?php echo e(old('email')); ?>" required autofocus><?php $__errorArgs = ['email'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?><div class="field-error"><?php echo e($message); ?></div><?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?></div><button class="btn-primary" type="submit" style="width:100%">Send Reset Link</button></form><div class="auth-links"><a href="<?php echo e(route('login')); ?>">Back to login</a></div></div></div>
</body></html>
<?php /**PATH /Applications/XAMPP/xamppfiles/htdocs/laravel/project_work/resources/views/auth/forgot-password.blade.php ENDPATH**/ ?>