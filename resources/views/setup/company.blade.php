@extends('layouts.app')

@section('title', 'Company Setup | Accounting System')

@section('content')
@php
    $isCompanyCompleted = (bool) $company;
@endphp

<div class="page-title">
    <div>
        <span class="page-label">Company Setup</span>
        <h2>Company Setup</h2>
        <p>Configure company profile, financial year, currency, and contact information.</p>
    </div>

    @if($isCompanyCompleted)
        <button class="btn-primary" type="button" data-company-edit-trigger>
            Edit Company Setup
        </button>
    @else
        <button class="btn-outline" type="button" data-toast="Import company information will be connected later.">
            Import Company Info
        </button>
    @endif
</div>

@include('partials.setup-progress', ['current' => 1])

<div class="layout">
    <form
        class="card form-card company-setup-form {{ $isCompanyCompleted ? 'is-company-readonly' : 'is-company-editing' }}"
        data-frontend-form
        data-company-setup-form
        data-company-completed="{{ $isCompanyCompleted ? '1' : '0' }}"
        data-action="{{ route('api.company.store') }}"
        data-success="Company setup saved. Moving to Chart of Accounts."
        enctype="multipart/form-data"
    >
        @csrf

        @if($isCompanyCompleted)
            <div class="company-view-banner" data-company-readonly-banner>
                <div>
                    <strong>Company setup is completed.</strong>
                    <span>This page is now in view-only mode. Click Edit Company Setup before changing any information.</span>
                </div>
                <button class="btn-outline" type="button" data-company-edit-trigger>
                    Edit
                </button>
            </div>
        @endif

        <fieldset class="company-fields" data-company-fields @disabled($isCompanyCompleted)>
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
                        <label>Business Type <span class="required">*</span></label>
                        <select
                            id="businessType"
                            name="business_type_id"
                            required
                            data-dropdown="/api/dropdowns/business-types"
                            data-placeholder="Select Business Type"
                            data-selected="{{ old('business_type_id', $company->business_type_id ?? '') }}"
                        ></select>
                        <div class="hint">Required company category used for setup defaults and reporting context.</div>
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
                        <label>BIN / VAT Registration No.</label>
                        <input
                            name="bin_vat_registration_no"
                            value="{{ old('bin_vat_registration_no', $company->bin_vat_registration_no ?? $company->tax_id_bin ?? '') }}"
                            placeholder="Enter BIN or VAT registration no."
                        >
                    </div>

                    <div>
                        <label>TIN</label>
                        <input
                            name="tin"
                            value="{{ old('tin', $company->tin ?? '') }}"
                            placeholder="Enter TIN"
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
                        <label>Accounting Method <span class="required">*</span></label>
                        <select name="accounting_method" required>
                            <option value="Accrual" @selected(old('accounting_method', $company->accounting_method ?? 'Accrual') === 'Accrual')>Accrual</option>
                            <option value="Cash" @selected(old('accounting_method', $company->accounting_method ?? 'Accrual') === 'Cash')>Cash</option>
                        </select>
                        <div class="hint">Default Accrual is recommended for SME reports.</div>
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

                    <div class="span-2">
                        <label>Current Financial Year <span class="required">*</span></label>
                        <select name="financial_year_id" required>
                            <option value="">Select Financial Year from Master Setup</option>
                            @foreach($financialYears as $financialYear)
                                <option
                                    value="{{ $financialYear->id }}"
                                    @selected((int) old('financial_year_id', $selectedFinancialYearId) === (int) $financialYear->id)
                                >
                                    {{ $financialYear->name }}
                                    — {{ optional($financialYear->start_date)->format('d M Y') }} to {{ optional($financialYear->end_date)->format('d M Y') }}
                                    @if($financialYear->is_current || $financialYear->is_active)
                                        (Current)
                                    @endif
                                </option>
                            @endforeach
                        </select>
                        <div class="hint">Financial years are maintained in Master Setup. The selected year is used as the default period across transactions, reports, opening balance, voucher numbering, and dashboard filters.</div>
                        @if($financialYears->isEmpty())
                            <div class="hint" style="color:#dc2626">Create a Financial Year in Master Setup before completing Company Setup.</div>
                        @endif
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

                    <div>
                        <label>Company Status <span class="required">*</span></label>
                        <select name="status" required>
                            <option value="Active" @selected(old('status', $company->status ?? 'Active') === 'Active')>Active</option>
                            <option value="Inactive" @selected(old('status', $company->status ?? 'Active') === 'Inactive')>Inactive</option>
                        </select>
                        <div class="hint">Inactive company blocks new postings.</div>
                    </div>
                </div>
            </div>
        </fieldset>

        <div class="actions company-form-actions" data-company-edit-actions @if($isCompanyCompleted) hidden @endif>
            @if($isCompanyCompleted)
                <button class="btn-ghost" type="button" data-company-cancel-edit>Cancel</button>
            @else
                <button class="btn-ghost" type="button" data-toast="Cancelled.">Cancel</button>
            @endif
            <button class="btn-outline" type="button" data-toast="Draft saved. You can continue setup later.">Save Draft</button>
            <button class="btn-primary" type="submit">{{ $isCompanyCompleted ? 'Update & Next →' : 'Save & Next →' }}</button>
        </div>

        @if($isCompanyCompleted)
            <div class="actions company-view-actions" data-company-view-actions>
                <button class="btn-primary" type="button" data-company-edit-trigger>
                    Edit Company Setup
                </button>
            </div>
        @endif
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
