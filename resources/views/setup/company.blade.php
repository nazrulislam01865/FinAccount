@extends('layouts.app')

@section('title', 'Company Setup | Accounting System')

@section('content')
<div class="page-title">
    <div>
        <span class="page-label">Company Setup</span>
        <h2>Company Setup</h2>
        <p>Configure company profile, financial year, currency, and contact information.</p>
    </div>

    <button class="btn-outline" type="button" data-toast="Import company information will be connected later.">
        Import Company Info
    </button>
</div>

@include('partials.setup-progress', ['current' => 1])

<div class="layout">
    <form
        class="card form-card"
        data-frontend-form
        data-action="{{ route('api.company.store') }}"
        data-success="Company setup saved. Moving to Chart of Accounts."
        enctype="multipart/form-data"
    >
        @csrf

        <div class="section">
            <h3 class="section-title">Basic Information</h3>

            <div class="grid">
                <div>
                    <label>Company Name <span class="required">*</span></label>
                    <input
                        name="company_name"
                        value="{{ old('company_name', $company->company_name ?? '') }}"
                        placeholder="Enter company name"
                        required
                    >
                    <div class="hint">Legal name of the company</div>
                </div>

                <div>
                    <label>Short Name <span class="required">*</span></label>
                    <input
                        name="short_name"
                        value="{{ old('short_name', $company->short_name ?? '') }}"
                        placeholder="Enter short name"
                        required
                    >
                    <div class="hint">Used in reports and dashboard</div>
                </div>

                <div>
                    <label>Business Type</label>
                    <select
                        id="businessType"
                        name="business_type_id"
                        data-dropdown="/api/dropdowns/business-types"
                        data-placeholder="Select Business Type"
                        data-selected="{{ old('business_type_id', $company->business_type_id ?? '') }}"
                    ></select>
                    <div class="hint">Optional business category</div>
                </div>

                <div>
                    <label>Trade License No.</label>
                    <input
                        name="trade_license_no"
                        value="{{ old('trade_license_no', $company->trade_license_no ?? '') }}"
                        placeholder="Enter trade license no."
                    >
                </div>

                <div>
                    <label>Tax ID / BIN</label>
                    <input
                        name="tax_id_bin"
                        value="{{ old('tax_id_bin', $company->tax_id_bin ?? '') }}"
                        placeholder="Enter tax ID or BIN"
                    >
                </div>

                <div>
                    <label>Currency <span class="required">*</span></label>
                    <select
                        id="currency"
                        name="currency_id"
                        required
                        data-dropdown="/api/dropdowns/currencies"
                        data-label="currency"
                        data-placeholder="Select Currency"
                        data-selected="{{ old('currency_id', $company->currency_id ?? '') }}"
                    ></select>
                </div>

                <div>
                    <label>Time Zone <span class="required">*</span></label>
                    <select
                        id="timeZone"
                        name="time_zone_id"
                        required
                        data-dropdown="/api/dropdowns/time-zones"
                        data-label="timezone"
                        data-placeholder="Select Time Zone"
                        data-selected="{{ old('time_zone_id', $company->time_zone_id ?? '') }}"
                    ></select>
                </div>

                <div>
                    <label>Financial Year Start <span class="required">*</span></label>
                    <input
                        type="date"
                        name="financial_year_start"
                        value="{{ old('financial_year_start', optional($company?->financial_year_start)->format('Y-m-d') ?? '') }}"
                        required
                    >
                </div>

                <div>
                    <label>Financial Year End <span class="required">*</span></label>
                    <input
                        type="date"
                        name="financial_year_end"
                        value="{{ old('financial_year_end', optional($company?->financial_year_end)->format('Y-m-d') ?? '') }}"
                        required
                    >
                </div>
            </div>
        </div>

        <div class="section">
            <h3 class="section-title">Contact & Address</h3>

            <div class="grid">
                <div class="span-2">
                    <label>Registered Address</label>
                    <textarea
                        name="address"
                        placeholder="Enter registered address"
                    >{{ old('address', $company->address ?? '') }}</textarea>
                </div>

                <div>
                    <label>Company Logo</label>
                    <input type="file" name="logo" accept="image/png,image/jpeg">
                    <div class="hint">PNG/JPG, max 2MB</div>

                    @if(!empty($company?->logo_path))
                        <div class="hint">Current logo: {{ $company->logo_path }}</div>
                    @endif
                </div>

                <div>
                    <label>Contact Email</label>
                    <input
                        type="email"
                        name="contact_email"
                        value="{{ old('contact_email', $company->contact_email ?? '') }}"
                        placeholder="accounts@example.com"
                    >
                </div>

                <div>
                    <label>Contact Phone</label>
                    <input
                        name="contact_phone"
                        value="{{ old('contact_phone', $company->contact_phone ?? '') }}"
                        placeholder="+880 1XXXXXXXXX"
                    >
                </div>

                <div>
                    <label>Website</label>
                    <input
                        name="website"
                        value="{{ old('website', $company->website ?? '') }}"
                        placeholder="www.example.com"
                    >
                </div>
            </div>
        </div>


        <div class="actions">
            <button class="btn-ghost" type="button" data-toast="Cancelled.">Cancel</button>
            <button class="btn-outline" type="button" data-toast="Draft saved. You can continue setup later.">Save Draft</button>
            <button class="btn-primary" type="submit">Save & Next →</button>
        </div>
    </form>

    <aside class="right-stack">
<div class="card info-card">
            <h3>Why this setup matters</h3>
            <p>These settings will be used in company identity, financial-year filtering, transaction entry, and accounting reports.</p>
        </div>

        <div class="card info-card">
            <h3>Dynamic Data</h3>
            <p>Business Types, Currencies, Settlement Types, Party Types, and Financial Years can be managed from Master Data. Time Zone is loaded from backend master tables.</p>
        </div>
    </aside>
</div>
@endsection
