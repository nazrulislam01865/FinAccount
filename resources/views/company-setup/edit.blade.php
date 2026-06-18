@php
    $brand = \App\Support\HisebGhorBrand::data();
    $isComplete = $company->isSetupComplete();
    $canOpenRelatedMasters = auth()->user()?->canAnyAccounting([
        'business_types.view', 'business_types.manage',
        'currencies.view', 'currencies.manage',
        'time_zones.view', 'time_zones.manage',
        'financial_years.view', 'financial_years.manage',
        'party_types.view', 'party_types.manage',
        'money_account_types.view', 'money_account_types.manage',
        'transaction_categories.view', 'transaction_categories.manage',
        'voucher_numbering.view', 'voucher_numbering.manage',
    ]) ?? false;
@endphp

<x-layouts::accounting title="Company Setup">
    <div class="hg-page-header">
        <div>
            <div class="hg-page-kicker">Configuration</div>
            <h1>Company Setup</h1>
        </div>
        <div class="hg-actions">
            <span class="hg-badge {{ $isComplete ? 'on' : 'payment' }}">{{ $isComplete ? 'Setup complete' : 'Setup incomplete' }}</span>
            <span class="hg-badge {{ $company->isActiveForPosting() ? 'sales' : 'payment' }}">{{ ucfirst($company->status) }}</span>
        </div>
    </div>

    @unless($canManage)
        <div class="hg-info" style="margin-bottom:16px">Your role can view Company Setup but cannot change it.</div>
    @endunless

    <div class="hg-company-setup-layout">
        <form method="POST" action="{{ route('company-setup.update') }}" class="hg-company-setup-form" data-draft-form data-draft-key="company-setup" data-draft-title="Company Setup">
            @csrf
            @method('PUT')
            <fieldset @disabled(! $canManage)>
                @include('company-setup.partials.identity')
                @include('company-setup.partials.legal')
                @include('company-setup.partials.accounting')
                @include('company-setup.partials.contact')
            </fieldset>

            @if($canManage)
                <div class="hg-company-form-actions">
                    <x-accounting.form-actions submit-label="Save Company Setup" />
                </div>
            @endif
        </form>

        <aside class="hg-company-side-stack">
            <section class="hg-card hg-company-summary-card">
                <h2 class="hg-card-title">Current Setup</h2>
                <dl class="hg-company-summary-list">
                    <div><dt>Company code</dt><dd>{{ $company->code }}</dd></div>
                    <div><dt>Business type</dt><dd>{{ $company->businessType?->name ?? 'Not selected' }}</dd></div>
                    <div><dt>Currency</dt><dd>{{ $company->currency ? $company->currency->code.' — '.$company->currency->name : 'Not selected' }}</dd></div>
                    <div><dt>Time zone</dt><dd>{{ $company->timeZone?->php_timezone ?? 'Not selected' }}</dd></div>
                    <div><dt>Current year</dt><dd>{{ $company->defaultFinancialYear?->name ?? 'Not selected' }}</dd></div>
                    <div><dt>Accounting method</dt><dd>{{ ucfirst($company->accounting_method ?: 'accrual') }}</dd></div>
                </dl>
            </section>

            <section class="hg-card hg-company-summary-card">
                <h2 class="hg-card-title">Company Branding</h2>
                <div class="hg-company-logo-preview">
                    @if($brand['logo_url'])
                        <img src="{{ $brand['logo_url'] }}" alt="{{ $company->short_name ?: $company->name }} logo">
                    @else
                        <div class="hg-brand-fallback">HG</div>
                    @endif
                </div>
                @if($canManageBranding)
                    <a class="hg-btn" href="{{ route('system.settings.index') }}">Manage Branding</a>
                @endif
            </section>

            <section class="hg-card hg-company-summary-card">
                <h2 class="hg-card-title">Related Master Data</h2>
                @if($canOpenRelatedMasters)
                    <a class="hg-btn" href="{{ route('master.overview') }}">Open Other Master Data</a>
                @endif
            </section>
        </aside>
    </div>
</x-layouts::accounting>
