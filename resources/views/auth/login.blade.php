<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login | Accounting System</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body>
<div class="auth-shell">
    <div class="auth-card">
        <div class="brand" style="border-bottom:0;padding:0;margin-bottom:20px;justify-content:center">
            <div class="brand-mark">▥</div>
            <div><h1>FinAcco</h1><p>Accounting System</p></div>
        </div>
        <h2 class="auth-title">Login</h2>
        <p class="auth-subtitle">Access your Sprint 1 accounting setup workspace.</p>

        @if (session('status'))
            <div class="alert-success">{{ session('status') }}</div>
        @endif

        <form method="POST" action="{{ route('login') }}" class="auth-form">
            @csrf
            <div>
                <label>Email</label>
                <input type="email" name="email" value="{{ old('email', 'admin@example.com') }}" required autofocus>
                @error('email')<div class="field-error">{{ $message }}</div>@enderror
            </div>
            <div>
                <label>Password</label>
                <input type="password" name="password" value="password" required>
                @error('password')<div class="field-error">{{ $message }}</div>@enderror
            </div>
            <label class="auth-check"><input type="checkbox" name="remember"> Remember me</label>
            <button class="btn-primary" type="submit" style="width:100%">Login</button>
        </form>
        <div class="auth-links">
            <a href="{{ route('password.request') }}">Forgot password?</a>
            <a href="{{ route('register') }}">Create account</a>
        </div>
    </div>
</div>
</body>
</html>
