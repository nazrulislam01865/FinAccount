<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Landing Admin Login | HisebGhor</title>
    <?php echo app('Illuminate\Foundation\Vite')(['resources/css/app.css', 'resources/js/app.js']); ?>
    <style>
        body{background:#f8fafc}.landing-admin-login-note{margin-top:12px;color:#667085;font-size:13px;line-height:1.5;text-align:center}.landing-admin-login-badge{display:inline-flex;align-items:center;justify-content:center;border-radius:999px;padding:7px 12px;background:#e9fff5;color:#087a52;font-weight:900;font-size:12px;margin-bottom:12px}.landing-admin-url{margin-top:18px;padding:12px;border:1px solid #e5e7eb;border-radius:14px;background:#f8fafc;color:#475467;font-size:13px;text-align:center}
    </style>
</head>
<body>
<div class="auth-shell">
    <div class="auth-card">
        <a href="<?php echo e(url('/')); ?>" class="brand brand-home" style="border-bottom:0;padding:0;margin-bottom:18px;justify-content:center" aria-label="Go to home">
            <div class="brand-mark">হি</div>
            <div><h1>HisebGhor</h1><p>Landing Page Admin</p></div>
        </a>

        <div style="text-align:center"><span class="landing-admin-login-badge">Separate Landing Access</span></div>
        <h2 class="auth-title">Landing Admin Login</h2>
        <p class="auth-subtitle">Use only the dedicated Landing Admin credentials from your server .env file.</p>

        <?php if(session('status')): ?>
            <div class="alert-success"><?php echo e(session('status')); ?></div>
        <?php endif; ?>

        <?php if((int) session('landing_admin_lockout_seconds', 0) > 0): ?>
            <div class="auth-lockout" data-login-countdown data-seconds="<?php echo e((int) session('landing_admin_lockout_seconds', 0)); ?>" aria-live="polite">
                <strong>Landing Admin login temporarily locked</strong>
                <span data-login-countdown-text>Calculating remaining time...</span>
            </div>
        <?php endif; ?>

        <form method="POST" action="<?php echo e(route('landing-admin.login.store')); ?>" class="auth-form">
            <?php echo csrf_field(); ?>
            <div>
                <label>Email</label>
                <input type="email" name="email" value="<?php echo e(old('email')); ?>" required autofocus autocomplete="username">
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
                <input type="password" name="password" required autocomplete="current-password">
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
            <button class="btn-primary" type="submit" style="width:100%" data-login-submit>Login to Landing Admin</button>
        </form>

        <div class="landing-admin-url">
            Public demo users must use <strong>/login</strong>. Landing Admin uses <strong>/landing-admin</strong> only.
        </div>
    </div>
</div>
<style>
    .auth-lockout{margin:0 0 14px;padding:13px 14px;border:1px solid #fecaca;border-radius:14px;background:#fef2f2;color:#991b1b;font-size:13px;line-height:1.45;text-align:left}
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
<?php /**PATH /Applications/XAMPP/xamppfiles/htdocs/laravel/project_work/resources/views/landing/admin/login.blade.php ENDPATH**/ ?>