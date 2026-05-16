<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register | Accounting System</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body>
<div class="auth-shell">
    <div class="auth-card">
        <a href="{{ url('/') }}" class="brand brand-home" style="border-bottom:0;padding:0;margin-bottom:20px;justify-content:center" aria-label="Go to home">
            <div class="brand-mark">▥</div>
            <div><h1>FinAcco</h1><p>Accounting System</p></div>
        </a>
        <span class="page-label auth-page-label">Create Account</span>
        <h2 class="auth-title">Create Account</h2>
        <form method="POST" action="{{ route('register') }}" class="auth-form">
            @csrf
            <div><label>Name</label><input name="name" value="{{ old('name') }}" required>@error('name')<div class="field-error">{{ $message }}</div>@enderror</div>
            <div><label>Email</label><input type="email" name="email" value="{{ old('email') }}" required>@error('email')<div class="field-error">{{ $message }}</div>@enderror</div>
            <div><label>Password</label><input type="password" name="password" required>@error('password')<div class="field-error">{{ $message }}</div>@enderror</div>
            <div><label>Confirm Password</label><input type="password" name="password_confirmation" required></div>
            <button class="btn-primary" type="submit" style="width:100%">Register</button>
        </form>
        <div class="auth-links"><a href="{{ route('login') }}">Already registered? Login</a></div>
    </div>
</div>
</body>
</html>
