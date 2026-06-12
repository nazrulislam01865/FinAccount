<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registration Disabled | HisebGhor</title>
    <?php echo app('Illuminate\Foundation\Vite')(['resources/css/app.css', 'resources/js/app.js']); ?>
</head>
<body>
<div class="auth-shell">
    <div class="auth-card">
        <a href="<?php echo e(url('/')); ?>" class="brand brand-home" style="border-bottom:0;padding:0;margin-bottom:20px;justify-content:center" aria-label="Go to home">
            <div class="brand-mark">হি</div>
            <div><h1>HisebGhor</h1><p>Accounting System</p></div>
        </a>
        <span class="page-label auth-page-label">Registration Disabled</span>
        <h2 class="auth-title">Account creation is admin controlled</h2>
        <p class="auth-subtitle">Public signup is disabled. A Super Admin or Admin must create users from the Users & Roles page.</p>
        <a href="<?php echo e(route('login')); ?>" class="btn-primary" style="width:100%;display:inline-flex;justify-content:center;text-decoration:none">Go to Login</a>
    </div>
</div>
</body>
</html>
<?php /**PATH /Applications/XAMPP/xamppfiles/htdocs/laravel/project_work/resources/views/auth/register.blade.php ENDPATH**/ ?>