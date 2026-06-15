@props([
    'optionsRoute' => 'passkey.login-options',
    'submitRoute' => 'passkey.login',
    'label' => __('Sign in with a passkey'),
    'loadingLabel' => __('Authenticating...'),
    'separator' => __('Or continue with email'),
])

@assets
@vite('resources/js/passkeys.js')
@endassets

<div
    x-data="{
        supported: false,
        loading: false,
        error: null,
        updateSupport() {
            this.supported = Boolean(window.Passkeys?.isSupported());
        },
        init() {
            this.updateSupport();
            window.addEventListener('passkeys:ready', () => this.updateSupport(), { once: true });
        },
        async verify() {
            this.loading = true;
            this.error = null;
            try {
                const response = await window.Passkeys.verify({
                    routes: {
                        options: '{{ route($optionsRoute) }}',
                        submit: '{{ route($submitRoute) }}',
                    },
                });
                Livewire.navigate(response.redirect || '/dashboard');
            } catch (e) {
                if (e.constructor?.name !== 'UserCancelledError') {
                    this.error = e.message;
                }
            } finally {
                this.loading = false;
            }
        },
    }"
>
    <template x-if="supported">
        <div class="hg-passkey-block">
            <button
                type="button"
                class="hg-passkey-button"
                x-on:click="verify()"
                x-bind:disabled="loading"
            >
                <svg viewBox="0 0 24 24" fill="none" aria-hidden="true">
                    <path d="M12 3a6 6 0 0 0-6 6v2m12 0V9a6 6 0 0 0-6-6" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"/>
                    <path d="M8.5 10.5v2.3c0 3-1 4.6-2.2 6.2M12 8.5v4.7c0 3.5-1.2 5.9-2.8 7.8M15.5 10.5v3.2c0 2.9-.7 5.1-1.8 7.3M18.5 13.5c0 2.8-.4 4.9-1.1 6.5" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"/>
                </svg>
                <span x-show="!loading">{{ $label }}</span>
                <span x-show="loading" x-cloak>{{ $loadingLabel }}</span>
            </button>

            <p x-show="error" x-text="error" x-cloak class="hg-passkey-error"></p>

            <div class="hg-auth-separator" aria-hidden="true">
                <span>{{ $separator }}</span>
            </div>
        </div>
    </template>
</div>
