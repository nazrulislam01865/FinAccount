<section class="hg-card hg-company-section">
    <div class="hg-section-heading"><div><h2>Contact & Address</h2></div></div>
    <div class="hg-form-grid">
        <div class="hg-field full">
            <label for="company-address">Registered Address</label>
            <textarea id="company-address" name="address" maxlength="1000">{{ old('address', $company->address) }}</textarea>
            @error('address')<small class="hg-field-error">{{ $message }}</small>@enderror
        </div>
        <div class="hg-field">
            <label for="company-email">Contact Email</label>
            <input id="company-email" type="email" name="contact_email" value="{{ old('contact_email', $company->contact_email) }}" maxlength="255">
            @error('contact_email')<small class="hg-field-error">{{ $message }}</small>@enderror
        </div>
        <div class="hg-field">
            <label for="company-phone">Contact Phone</label>
            <input id="company-phone" name="contact_phone" value="{{ old('contact_phone', $company->contact_phone) }}" maxlength="50">
            @error('contact_phone')<small class="hg-field-error">{{ $message }}</small>@enderror
        </div>
        <div class="hg-field">
            <label for="company-website">Website</label>
            <input id="company-website" name="website" value="{{ old('website', $company->website) }}" maxlength="255" placeholder="https://example.com">
            @error('website')<small class="hg-field-error">{{ $message }}</small>@enderror
        </div>
    </div>
</section>
