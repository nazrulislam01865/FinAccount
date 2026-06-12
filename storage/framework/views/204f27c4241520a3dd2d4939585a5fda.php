<?php $__env->startSection('title', 'Company Setup | HisebGhor'); ?>

<?php $__env->startSection('content'); ?>
<?php
    $isCompanyCompleted = (bool) $company;
?>

<div class="page-title">
    <div>
        <span class="page-label">Company Setup</span>
        <h2>Company Setup</h2>
        <p>Configure company profile, financial year, currency, and contact information.</p>
    </div>

    <?php if($isCompanyCompleted): ?>
        <button class="btn-primary" type="button" data-company-edit-trigger>
            Edit Company Setup
        </button>
    <?php else: ?>
        <button class="btn-outline" type="button" data-toast="Import company information will be connected later.">
            Import Company Info
        </button>
    <?php endif; ?>
</div>

<?php echo $__env->make('partials.setup-progress', ['current' => 1], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>

<div class="layout">
    <form
        class="card form-card company-setup-form <?php echo e($isCompanyCompleted ? 'is-company-readonly' : 'is-company-editing'); ?>"
        data-frontend-form
        data-company-setup-form
        data-company-completed="<?php echo e($isCompanyCompleted ? '1' : '0'); ?>"
        data-action="<?php echo e(route('api.company.store')); ?>"
        data-success="Company setup saved. Moving to Chart of Accounts."
        enctype="multipart/form-data"
    >
        <?php echo csrf_field(); ?>

        <?php if($isCompanyCompleted): ?>
            <div class="company-view-banner" data-company-readonly-banner>
                <div>
                    <strong>Company setup is completed.</strong>
                    <span>This page is now in view-only mode. Click Edit Company Setup before changing any information.</span>
                </div>
                <button class="btn-outline" type="button" data-company-edit-trigger>
                    Edit
                </button>
            </div>
        <?php endif; ?>

        <fieldset class="company-fields" data-company-fields <?php if($isCompanyCompleted): echo 'disabled'; endif; ?>>
            <div class="section">
                <h3 class="section-title">Basic Information</h3>

                <div class="grid">
                    <div>
                        <label>Company Name <span class="required">*</span></label>
                        <input
                            name="company_name"
                            value="<?php echo e(old('company_name', $company->company_name ?? '')); ?>"
                            placeholder="Enter company name"
                            required
                        >
                        <div class="hint">Legal name of the company</div>
                    </div>

                    <div>
                        <label>Short Name <span class="required">*</span></label>
                        <input
                            name="short_name"
                            value="<?php echo e(old('short_name', $company->short_name ?? '')); ?>"
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
                            data-selected="<?php echo e(old('business_type_id', $company->business_type_id ?? '')); ?>"
                        ></select>
                        <div class="hint">Required company category used for setup defaults and reporting context.</div>
                    </div>

                    <div>
                        <label>Trade License No.</label>
                        <input
                            name="trade_license_no"
                            value="<?php echo e(old('trade_license_no', $company->trade_license_no ?? '')); ?>"
                            placeholder="Enter trade license no."
                        >
                    </div>

                    <div>
                        <label>BIN / VAT Registration No.</label>
                        <input
                            name="bin_vat_registration_no"
                            value="<?php echo e(old('bin_vat_registration_no', $company->bin_vat_registration_no ?? $company->tax_id_bin ?? '')); ?>"
                            placeholder="Enter BIN or VAT registration no."
                        >
                    </div>

                    <div>
                        <label>TIN</label>
                        <input
                            name="tin"
                            value="<?php echo e(old('tin', $company->tin ?? '')); ?>"
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
                            data-selected="<?php echo e(old('currency_id', $company->currency_id ?? '')); ?>"
                        ></select>
                    </div>

                    <div>
                        <label>Accounting Method <span class="required">*</span></label>
                        <select name="accounting_method" required>
                            <option value="Accrual" <?php if(old('accounting_method', $company->accounting_method ?? 'Accrual') === 'Accrual'): echo 'selected'; endif; ?>>Accrual</option>
                            <option value="Cash" <?php if(old('accounting_method', $company->accounting_method ?? 'Accrual') === 'Cash'): echo 'selected'; endif; ?>>Cash</option>
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
                            data-selected="<?php echo e(old('time_zone_id', $company->time_zone_id ?? '')); ?>"
                        ></select>
                    </div>

                    <div class="span-2">
                        <label>Current Financial Year <span class="required">*</span></label>
                        <select name="financial_year_id" required>
                            <option value="">Select Financial Year from Master Setup</option>
                            <?php $__currentLoopData = $financialYears; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $financialYear): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                <option
                                    value="<?php echo e($financialYear->id); ?>"
                                    <?php if((int) old('financial_year_id', $selectedFinancialYearId) === (int) $financialYear->id): echo 'selected'; endif; ?>
                                >
                                    <?php echo e($financialYear->name); ?>

                                    — <?php echo e(optional($financialYear->start_date)->format('d M Y')); ?> to <?php echo e(optional($financialYear->end_date)->format('d M Y')); ?>

                                    <?php if($financialYear->is_current || $financialYear->is_active): ?>
                                        (Current)
                                    <?php endif; ?>
                                </option>
                            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                        </select>
                        <div class="hint">Financial years are maintained in Master Setup. The selected year is used as the default period across transactions, reports, opening balance, voucher numbering, and dashboard filters.</div>
                        <?php if($financialYears->isEmpty()): ?>
                            <div class="hint" style="color:#dc2626">Create a Financial Year in Master Setup before completing Company Setup.</div>
                        <?php endif; ?>
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
                        ><?php echo e(old('address', $company->address ?? '')); ?></textarea>
                    </div>

                    <div>
                        <label>Company Logo</label>
                        <input type="file" name="logo" accept="image/png,image/jpeg">
                        <div class="hint">PNG/JPG, max 2MB</div>

                        <?php if(!empty($company?->logo_path)): ?>
                            <div class="hint">Current logo: <?php echo e($company->logo_path); ?></div>
                        <?php endif; ?>
                    </div>

                    <div>
                        <label>Contact Email</label>
                        <input
                            type="email"
                            name="contact_email"
                            value="<?php echo e(old('contact_email', $company->contact_email ?? '')); ?>"
                            placeholder="accounts@example.com"
                        >
                    </div>

                    <div>
                        <label>Contact Phone</label>
                        <input
                            name="contact_phone"
                            value="<?php echo e(old('contact_phone', $company->contact_phone ?? '')); ?>"
                            placeholder="+880 1XXXXXXXXX"
                        >
                    </div>

                    <div>
                        <label>Website</label>
                        <input
                            name="website"
                            value="<?php echo e(old('website', $company->website ?? '')); ?>"
                            placeholder="www.example.com"
                        >
                    </div>

                    <div>
                        <label>Company Status <span class="required">*</span></label>
                        <select name="status" required>
                            <option value="Active" <?php if(old('status', $company->status ?? 'Active') === 'Active'): echo 'selected'; endif; ?>>Active</option>
                            <option value="Inactive" <?php if(old('status', $company->status ?? 'Active') === 'Inactive'): echo 'selected'; endif; ?>>Inactive</option>
                        </select>
                        <div class="hint">Inactive company blocks new postings.</div>
                    </div>
                </div>
            </div>
        </fieldset>

        <div class="actions company-form-actions" data-company-edit-actions <?php if($isCompanyCompleted): ?> hidden <?php endif; ?>>
            <?php if($isCompanyCompleted): ?>
                <button class="btn-ghost" type="button" data-company-cancel-edit>Cancel</button>
            <?php else: ?>
                <button class="btn-ghost" type="button" data-toast="Cancelled.">Cancel</button>
            <?php endif; ?>
            <button class="btn-outline" type="button" data-toast="Draft saved. You can continue setup later.">Save Draft</button>
            <button class="btn-primary" type="submit"><?php echo e($isCompanyCompleted ? 'Update & Next →' : 'Save & Next →'); ?></button>
        </div>

        <?php if($isCompanyCompleted): ?>
            <div class="actions company-view-actions" data-company-view-actions>
                <button class="btn-primary" type="button" data-company-edit-trigger>
                    Edit Company Setup
                </button>
            </div>
        <?php endif; ?>
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
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH /Applications/XAMPP/xamppfiles/htdocs/laravel/project_work/resources/views/setup/company.blade.php ENDPATH**/ ?>