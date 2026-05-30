<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login | HisebGhor</title>
    <?php echo app('Illuminate\Foundation\Vite')(['resources/css/app.css', 'resources/js/app.js']); ?>
</head>
<body>
<div class="auth-shell">
    <div class="auth-card">
        <a href="<?php echo e(url('/')); ?>" class="brand brand-home" style="border-bottom:0;padding:0;margin-bottom:20px;justify-content:center" aria-label="Go to home">
            <div class="brand-mark">হি</div>
            <div><h1>HisebGhor</h1><p>Accounting System</p></div>
        </a>
        <span class="page-label auth-page-label">Login</span>
        <h2 class="auth-title">Login</h2>
        <p class="auth-subtitle">Access your Sprint 1 accounting setup workspace.</p>

        <?php if(session('status')): ?>
            <div class="alert-success"><?php echo e(session('status')); ?></div>
        <?php endif; ?>

        <form method="POST" action="<?php echo e(route('login')); ?>" class="auth-form">
            <?php echo csrf_field(); ?>
            <div>
                <label>Email</label>
                <input type="email" name="email" value="<?php echo e(old('email')); ?>" required autofocus>
                <?php $__errorArgs = ['email'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?><div class="field-error"><?php echo e($message); ?></div><?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>
            </div>
            <div>
                <label>Password</label>
                <input type="password" name="password" required>
                <?php $__errorArgs = ['password'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?><div class="field-error"><?php echo e($message); ?></div><?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>
            </div>
            <label class="auth-check"><input type="checkbox" name="remember"> Remember me</label>
            <button class="btn-primary" type="submit" style="width:100%">Login</button>
        </form>
        <div class="auth-links">
            <a href="<?php echo e(route('password.request')); ?>">Forgot password?</a>
            <span>New users are created by Super Admin/Admin only.</span>
        </div>
    </div>
</div>
</body>
</html>
<?php /**PATH /Applications/XAMPP/xamppfiles/htdocs/laravel/project_work/resources/views/auth/login.blade.php ENDPATH**/ ?>