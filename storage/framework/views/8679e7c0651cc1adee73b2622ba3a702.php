<!DOCTYPE html>
<html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title>Reset Password</title><?php echo app('Illuminate\Foundation\Vite')(['resources/css/app.css', 'resources/js/app.js']); ?></head><body>
<div class="auth-shell"><div class="auth-card"><span class="page-label auth-page-label">Reset Password</span><h2 class="auth-title">Reset Password</h2><form method="POST" action="<?php echo e(route('password.store')); ?>" class="auth-form"><?php echo csrf_field(); ?><input type="hidden" name="token" value="<?php echo e($request->route('token')); ?>"><div><label>Email</label><input type="email" name="email" value="<?php echo e(old('email', $request->email)); ?>" required><?php $__errorArgs = ['email'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?><div class="field-error"><?php echo e($message); ?></div><?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?></div><div><label>Password</label><input type="password" name="password" required><?php $__errorArgs = ['password'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?><div class="field-error"><?php echo e($message); ?></div><?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?></div><div><label>Confirm Password</label><input type="password" name="password_confirmation" required></div><button class="btn-primary" type="submit" style="width:100%">Reset Password</button></form></div></div>
</body></html>
<?php /**PATH /Applications/XAMPP/xamppfiles/htdocs/laravel/project_work/resources/views/auth/reset-password.blade.php ENDPATH**/ ?>