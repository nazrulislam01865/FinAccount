<x-layouts::auth :title="__('Sign in')">
    <div class="hg-login-card">
        <header class="hg-login-header">
            <span class="hg-login-kicker">Welcome back</span>
            <h2>Sign in to HisebGhor</h2>
            <p>Enter your account details to continue to your accounting workspace.</p>
        </header>

        <x-auth-session-status class="hg-auth-status" :status="session('status')" />

        @if ($errors->any())
            <div class="hg-auth-error-summary" role="alert">
                <svg viewBox="0 0 24 24" fill="none" aria-hidden="true">
                    <path d="M12 8v4m0 4h.01" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                    <path d="M10.3 3.85 2.8 17a2 2 0 0 0 1.73 3h14.94a2 2 0 0 0 1.73-3L13.7 3.85a2 2 0 0 0-3.4 0Z" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/>
                </svg>
                <span>{{ __('We could not sign you in. Please check your details and try again.') }}</span>
            </div>
        @endif

        <x-passkey-verify />

        <form method="POST" action="{{ route('login.store') }}" class="hg-login-form">
            @csrf

            <div class="hg-auth-field">
                <label for="email">{{ __('Email address') }}</label>
                <div class="hg-auth-input-wrap @error('email') has-error @enderror">
                    <span class="hg-auth-input-icon" aria-hidden="true">
                        <svg viewBox="0 0 24 24" fill="none">
                            <path d="m4 6 8 6 8-6" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
                            <rect x="3" y="5" width="18" height="14" rx="3" stroke="currentColor" stroke-width="1.8"/>
                        </svg>
                    </span>
                    <input
                        id="email"
                        name="email"
                        value="{{ old('email') }}"
                        type="email"
                        required
                        autofocus
                        autocomplete="email"
                        placeholder="name@company.com"
                        aria-invalid="{{ $errors->has('email') ? 'true' : 'false' }}"
                        @error('email') aria-describedby="email-error" @enderror
                    >
                </div>
                @error('email')
                    <span class="hg-auth-field-error" id="email-error">{{ $message }}</span>
                @enderror
            </div>

            <div class="hg-auth-field" x-data="{ showPassword: false }">
                <div class="hg-auth-label-row">
                    <label for="password">{{ __('Password') }}</label>
                    @if (Route::has('password.request'))
                        <a href="{{ route('password.request') }}" class="hg-auth-link" wire:navigate>
                            {{ __('Forgot password?') }}
                        </a>
                    @endif
                </div>

                <div class="hg-auth-input-wrap @error('password') has-error @enderror">
                    <span class="hg-auth-input-icon" aria-hidden="true">
                        <svg viewBox="0 0 24 24" fill="none">
                            <path d="M7 10V8a5 5 0 0 1 10 0v2" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
                            <rect x="4" y="10" width="16" height="11" rx="3" stroke="currentColor" stroke-width="1.8"/>
                        </svg>
                    </span>
                    <input
                        id="password"
                        name="password"
                        x-bind:type="showPassword ? 'text' : 'password'"
                        type="password"
                        required
                        autocomplete="current-password"
                        placeholder="Enter your password"
                        aria-invalid="{{ $errors->has('password') ? 'true' : 'false' }}"
                        @error('password') aria-describedby="password-error" @enderror
                    >
                    <button
                        type="button"
                        class="hg-password-toggle"
                        x-on:click="showPassword = !showPassword"
                        x-bind:aria-label="showPassword ? @js(__('Hide password')) : @js(__('Show password'))"
                    >
                        <svg x-show="!showPassword" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                            <path d="M2.5 12s3.5-6 9.5-6 9.5 6 9.5 6-3.5 6-9.5 6-9.5-6-9.5-6Z" stroke="currentColor" stroke-width="1.7" stroke-linejoin="round"/>
                            <circle cx="12" cy="12" r="2.5" stroke="currentColor" stroke-width="1.7"/>
                        </svg>
                        <svg x-show="showPassword" x-cloak viewBox="0 0 24 24" fill="none" aria-hidden="true">
                            <path d="m3 3 18 18M10.6 6.2A9.8 9.8 0 0 1 12 6c6 0 9.5 6 9.5 6a16.5 16.5 0 0 1-3.1 3.7M6.2 6.2C3.8 8 2.5 12 2.5 12s3.5 6 9.5 6c1.4 0 2.6-.3 3.7-.7M9.9 9.9A3 3 0 0 0 14.1 14" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"/>
                        </svg>
                    </button>
                </div>
                @error('password')
                    <span class="hg-auth-field-error" id="password-error">{{ $message }}</span>
                @enderror
            </div>

            <label class="hg-remember-option" for="remember">
                <input id="remember" name="remember" type="checkbox" value="1" @checked(old('remember'))>
                <span>{{ __('Keep me signed in on this device') }}</span>
            </label>

            <button type="submit" class="hg-login-button" data-test="login-button">
                <span>{{ __('Sign in securely') }}</span>
                <svg viewBox="0 0 24 24" fill="none" aria-hidden="true">
                    <path d="M5 12h14m-5-5 5 5-5 5" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
            </button>
        </form>

        @if (Route::has('register'))
            <p class="hg-auth-register-copy">
                <span>{{ __('New to HisebGhor?') }}</span>
                <a href="{{ route('register') }}" class="hg-auth-link" wire:navigate>{{ __('Create an account') }}</a>
            </p>
        @endif

        <div class="hg-auth-security-note">
            <svg viewBox="0 0 24 24" fill="none" aria-hidden="true">
                <path d="M12 3 5 6v5c0 4.8 2.9 8.2 7 10 4.1-1.8 7-5.2 7-10V6l-7-3Z" stroke="currentColor" stroke-width="1.7" stroke-linejoin="round"/>
                <path d="m9.5 12 1.7 1.7 3.6-4" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"/>
            </svg>
            <span>Your sign-in is protected by secure session and rate-limit controls.</span>
        </div>
    </div>
</x-layouts::auth>
