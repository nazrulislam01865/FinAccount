<!DOCTYPE html>
<html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title>Confirm Password</title>@vite(['resources/css/app.css', 'resources/js/app.js'])</head><body>
<div class="auth-shell"><div class="auth-card"><span class="page-label auth-page-label">Confirm Password</span><h2 class="auth-title">Confirm Password</h2><p class="auth-subtitle">Confirm your password to continue.</p><form method="POST" action="{{ route('password.confirm') }}" class="auth-form">@csrf<div><label>Password</label><input type="password" name="password" required>@error('password')<div class="field-error">{{ $message }}</div>@enderror</div><button class="btn-primary" type="submit" style="width:100%">Confirm</button></form></div></div>
</body></html>
