<section class="hg-card hg-company-section">
    <div class="hg-section-heading"><div><h2>Company Identity</h2></div></div>
    <div class="hg-form-grid">
        <div class="hg-field">
            <label for="company-code">Company Code</label>
            <input id="company-code" value="{{ $company->code }}" readonly disabled>
        </div>
        <div class="hg-field">
            <label for="company-name">Legal Company Name <span class="hg-required">*</span></label>
            <input id="company-name" name="name" value="{{ old('name', $company->name) }}" maxlength="255" required>
            @error('name')<small class="hg-field-error">{{ $message }}</small>@enderror
        </div>
        <div class="hg-field">
            <label for="company-short-name">Short Name <span class="hg-required">*</span></label>
            <input id="company-short-name" name="short_name" value="{{ old('short_name', $company->short_name) }}" maxlength="120" required>
            @error('short_name')<small class="hg-field-error">{{ $message }}</small>@enderror
        </div>
        <div class="hg-field">
            <label for="company-business-type">Business Type <span class="hg-required">*</span></label>
            <select id="company-business-type" name="business_type_id" required>
                <option value="">Select Business Type</option>
                @foreach($businessTypes as $businessType)
                    <option value="{{ $businessType->id }}" @selected((string) old('business_type_id', $company->business_type_id) === (string) $businessType->id)>
                        {{ $businessType->code }} — {{ $businessType->name }}
                    </option>
                @endforeach
            </select>
            @if($businessTypes->isEmpty())<small class="hg-field-error">Create an active Business Type in Other Master Data.</small>@endif
            @error('business_type_id')<small class="hg-field-error">{{ $message }}</small>@enderror
        </div>
        <div class="hg-field">
            <label for="company-status">Company Status <span class="hg-required">*</span></label>
            <select id="company-status" name="status" required>
                <option value="active" @selected(old('status', $company->status) === 'active')>Active</option>
                <option value="inactive" @selected(old('status', $company->status) === 'inactive')>Inactive</option>
            </select>
            @error('status')<small class="hg-field-error">{{ $message }}</small>@enderror
        </div>
    </div>
</section>
