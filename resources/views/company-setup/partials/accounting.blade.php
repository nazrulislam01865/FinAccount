<section class="hg-card hg-company-section">
    <div class="hg-section-heading"><div><h2>Accounting Context</h2></div></div>
    <div class="hg-form-grid">
        <div class="hg-field">
            <label for="company-currency">Currency <span class="hg-required">*</span></label>
            <select id="company-currency" name="currency_id" required>
                <option value="">Select Currency</option>
                @foreach($currencies as $currency)
                    <option value="{{ $currency->id }}" @selected((string) old('currency_id', $company->currency_id) === (string) $currency->id)>
                        {{ $currency->code }} — {{ $currency->name }} {{ $currency->symbol ? '('.$currency->symbol.')' : '' }}
                    </option>
                @endforeach
            </select>
            @if($currencies->isEmpty())<small class="hg-field-error">Create an active Currency in Other Master Data.</small>@endif
            @error('currency_id')<small class="hg-field-error">{{ $message }}</small>@enderror
        </div>
        <div class="hg-field">
            <label for="accounting-method-display">Accounting Method</label>
            <input id="accounting-method-display" value="Accrual" readonly>
            <input type="hidden" name="accounting_method" value="accrual">
            @error('accounting_method')<small class="hg-field-error">{{ $message }}</small>@enderror
        </div>
        <div class="hg-field">
            <label for="company-time-zone">Time Zone <span class="hg-required">*</span></label>
            <select id="company-time-zone" name="time_zone_id" required>
                <option value="">Select Time Zone</option>
                @foreach($timeZones as $timeZone)
                    <option value="{{ $timeZone->id }}" @selected((string) old('time_zone_id', $company->time_zone_id) === (string) $timeZone->id)>
                        {{ $timeZone->name }} — {{ $timeZone->utc_offset }} ({{ $timeZone->php_timezone }})
                    </option>
                @endforeach
            </select>
            @if($timeZones->isEmpty())<small class="hg-field-error">Create an active Time Zone in Other Master Data.</small>@endif
            @error('time_zone_id')<small class="hg-field-error">{{ $message }}</small>@enderror
        </div>
        <div class="hg-field">
            <label for="company-financial-year">Current Financial Year <span class="hg-required">*</span></label>
            <select id="company-financial-year" name="default_financial_year_id" required>
                <option value="">Select Open Financial Year</option>
                @foreach($financialYears as $financialYear)
                    <option value="{{ $financialYear->id }}" @selected((string) old('default_financial_year_id', $company->default_financial_year_id) === (string) $financialYear->id)>
                        {{ $financialYear->name }} — {{ $financialYear->start_date->format('d M Y') }} to {{ $financialYear->end_date->format('d M Y') }}
                    </option>
                @endforeach
            </select>
            @if($financialYears->isEmpty())<small class="hg-field-error">Create an active Open Financial Year in Other Master Data.</small>@endif
            @error('default_financial_year_id')<small class="hg-field-error">{{ $message }}</small>@enderror
        </div>
        <div class="hg-field">
            <label for="default-branch">Default Branch / Location</label>
            <input id="default-branch" name="default_branch" value="{{ old('default_branch', $company->default_branch) }}" maxlength="150">
            @error('default_branch')<small class="hg-field-error">{{ $message }}</small>@enderror
        </div>
    </div>
</section>
