<!DOCTYPE html>
<html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title>Confirm Password</title><?php echo app('Illuminate\Foundation\Vite')(['resources/css/app.css', 'resources/js/app.js']); ?></head><body>
<div class="auth-shell"><div class="auth-card"><span class="page-label auth-page-label">Confirm Password</span><h2 class="auth-title">Confirm Password</h2><p class="auth-subtitle">Confirm your password to continue.</p><form method="POST" action="<?php echo e(route('password.confirm')); ?>" class="auth-form"><?php echo csrf_field(); ?><div><label>Password</label><input type="password" name="password" required><?php $__errorArgs = ['password'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?><div class="field-error"><?php echo e($message); ?></div><?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?></div><button class="btn-primary" type="submit" style="width:100%">Confirm</button></form></div></div>
</body></html>
<?php /**PATH /Applications/XAMPP/xamppfiles/htdocs/laravel/project_work/resources/views/auth/confirm-password.blade.php ENDPATH**/ ?>