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

        <?php if(request()->boolean('session_expired')): ?>
            <div class="auth-session-expired">Your current session expired, please login.</div>
        <?php endif; ?>

        <?php if((int) session('login_lockout_seconds', 0) > 0): ?>
            <div class="auth-lockout" data-login-countdown data-seconds="<?php echo e((int) session('login_lockout_seconds', 0)); ?>" aria-live="polite">
                <strong>Login temporarily locked</strong>
                <span data-login-countdown-text>Calculating remaining time...</span>
            </div>
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
            <button class="btn-primary" type="submit" style="width:100%" data-login-submit>Login</button>
        </form>
        <div class="auth-links">
            <a href="<?php echo e(route('password.request')); ?>">Forgot password?</a>
            <span>New users are created by Super Admin/Admin only.</span>
        </div>
    </div>
</div>
<style>
    .auth-lockout,.auth-session-expired{margin:0 0 14px;padding:13px 14px;border:1px solid #fecaca;border-radius:14px;background:#fef2f2;color:#991b1b;font-size:13px;line-height:1.45;text-align:left}
    .auth-lockout strong{display:block;margin-bottom:4px}.auth-lockout span{display:block}.btn-primary:disabled{opacity:.6;cursor:not-allowed;transform:none}
</style>
<script>
(function(){
    const box = document.querySelector('[data-login-countdown]');
    if (!box) return;

    const text = box.querySelector('[data-login-countdown-text]');
    const submit = document.querySelector('[data-login-submit]');
    let remaining = Number.parseInt(box.dataset.seconds || '0', 10);

    function format(seconds) {
        seconds = Math.max(0, seconds);
        const hours = Math.floor(seconds / 3600);
        const minutes = Math.floor((seconds % 3600) / 60);
        const secs = seconds % 60;

        if (hours > 0) {
            return `${hours}h ${String(minutes).padStart(2, '0')}m ${String(secs).padStart(2, '0')}s`;
        }

        return `${String(minutes).padStart(2, '0')}m ${String(secs).padStart(2, '0')}s`;
    }

    function tick() {
        if (remaining > 0) {
            if (submit) submit.disabled = true;
            text.textContent = `Please wait ${format(remaining)} before trying again.`;
            remaining -= 1;
            window.setTimeout(tick, 1000);
            return;
        }

        if (submit) submit.disabled = false;
        text.textContent = 'You can try logging in again now.';
        box.style.borderColor = '#bbf7d0';
        box.style.background = '#f0fdf4';
        box.style.color = '#067647';
    }

    tick();
})();
</script>
</body>
</html>
<?php /**PATH /Applications/XAMPP/xamppfiles/htdocs/laravel/project_work/resources/views/auth/login.blade.php ENDPATH**/ ?>