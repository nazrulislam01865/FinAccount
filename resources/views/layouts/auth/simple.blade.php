<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    @include('partials.head')
</head>
<body class="hg-auth-body">
    @php
        $brand = \App\Support\HisebGhorBrand::data();
    @endphp
    <main class="hg-auth-page">
        <section class="hg-auth-shell" aria-label="HisebGhor authentication">
            <aside class="hg-auth-intro">
                <div class="hg-auth-decoration hg-auth-decoration-one" aria-hidden="true"></div>
                <div class="hg-auth-decoration hg-auth-decoration-two" aria-hidden="true"></div>

                <a href="{{ route('home') }}" class="hg-auth-brand" wire:navigate>
                    @if(!empty($brand['logo_url']))
                        <span class="hg-auth-brand-mark hg-auth-brand-image"><img src="{{ $brand['logo_url'] }}" alt="{{ $brand['name'] }} logo"></span>
                    @else
                        <span class="hg-auth-brand-mark" aria-hidden="true">HG</span>
                    @endif
                    <span class="hg-auth-brand-copy">
                        <strong>{{ $brand['name'] ?? 'HisebGhor' }}</strong>
                        <small>Smart Accounting Workspace</small>
                    </span>
                </a>

                <div class="hg-auth-intro-content">
                    <span class="hg-auth-eyebrow">Accounting made understandable</span>
                    <h1>Run daily accounts with clarity and confidence.</h1>
                    <div class="hg-auth-benefits" aria-label="HisebGhor benefits">
                        <div class="hg-auth-benefit">
                            <span class="hg-auth-benefit-icon" aria-hidden="true">
                                <svg viewBox="0 0 24 24" fill="none">
                                    <path d="m7.5 12 3 3 6-7" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                                    <path d="M12 21a9 9 0 1 0 0-18 9 9 0 0 0 0 18Z" stroke="currentColor" stroke-width="1.7"/>
                                </svg>
                            </span>
                            <span><strong>Automatic journal flow</strong><small>Accounting rules handle debit and credit posting.</small></span>
                        </div>
                        <div class="hg-auth-benefit">
                            <span class="hg-auth-benefit-icon" aria-hidden="true">
                                <svg viewBox="0 0 24 24" fill="none">
                                    <path d="M4 19V9m5 10V5m6 14v-7m5 7V3" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
                                </svg>
                            </span>
                            <span><strong>Clear business overview</strong><small>See balances, transactions and statements together.</small></span>
                        </div>
                        <div class="hg-auth-benefit">
                            <span class="hg-auth-benefit-icon" aria-hidden="true">
                                <svg viewBox="0 0 24 24" fill="none">
                                    <path d="M7 10V8a5 5 0 0 1 10 0v2" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
                                    <rect x="4" y="10" width="16" height="11" rx="3" stroke="currentColor" stroke-width="1.8"/>
                                    <path d="M12 14v3" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
                                </svg>
                            </span>
                            <span><strong>Protected workspace</strong><small>Your company records stay inside your secured account.</small></span>
                        </div>
                    </div>
                </div>

                <p class="hg-auth-intro-footer">Simple setup. Reliable records. Better decisions.</p>
            </aside>

            <section class="hg-auth-panel">
                <div class="hg-auth-mobile-brand">
                    <a href="{{ route('home') }}" class="hg-auth-brand" wire:navigate>
                        @if(!empty($brand['logo_url']))
                        <span class="hg-auth-brand-mark hg-auth-brand-image"><img src="{{ $brand['logo_url'] }}" alt="{{ $brand['name'] }} logo"></span>
                    @else
                        <span class="hg-auth-brand-mark" aria-hidden="true">HG</span>
                    @endif
                        <span class="hg-auth-brand-copy">
                            <strong>{{ $brand['name'] ?? 'HisebGhor' }}</strong>
                            <small>Smart Accounting Workspace</small>
                        </span>
                    </a>
                </div>

                <div class="hg-auth-form-wrap">
                    {{ $slot }}
                </div>

                <x-accounting.footer :brand="$brand" class="hg-auth-system-footer" />
            </section>
        </section>
    </main>

    @persist('toast')
        <flux:toast.group>
            <flux:toast />
        </flux:toast.group>
    @endpersist

    @fluxScripts
</body>
</html>
