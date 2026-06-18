<section class="hg-card hg-company-section">
    <div class="hg-section-heading"><div><h2>Legal & Tax Information</h2></div></div>
    <div class="hg-form-grid">
        <div class="hg-field">
            <label for="trade-license">Trade License No.</label>
            <input id="trade-license" name="trade_license_no" value="{{ old('trade_license_no', $company->trade_license_no) }}" maxlength="100">
            @error('trade_license_no')<small class="hg-field-error">{{ $message }}</small>@enderror
        </div>
        <div class="hg-field">
            <label for="bin-vat">BIN / VAT Registration No.</label>
            <input id="bin-vat" name="bin_vat_registration_no" value="{{ old('bin_vat_registration_no', $company->bin_vat_registration_no) }}" maxlength="100">
            @error('bin_vat_registration_no')<small class="hg-field-error">{{ $message }}</small>@enderror
        </div>
        <div class="hg-field">
            <label for="tin">TIN</label>
            <input id="tin" name="tin" value="{{ old('tin', $company->tin) }}" maxlength="100">
            @error('tin')<small class="hg-field-error">{{ $message }}</small>@enderror
        </div>
    </div>
</section>
