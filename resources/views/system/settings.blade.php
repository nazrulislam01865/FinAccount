<x-layouts::accounting title="Branding Settings">
    <div class="hg-page-header"><div><h1>Company Branding Settings</h1><p>Manage the system logo and browser favicon. This page is permanently restricted to Super Admin.</p></div><span class="hg-badge sales">Super Admin only</span></div>
    <div class="hg-grid hg-grid-2">
        <section class="hg-card hg-brand-settings-card">
            <h2 class="hg-card-title">Company Logo</h2>
            <div class="hg-brand-preview">@if($brand['logo_url'])<img src="{{ $brand['logo_url'] }}" alt="Company logo">@else<div class="hg-brand-fallback">HG<br><small>HisebGhor</small></div>@endif</div>
            <form method="POST" action="{{ route('system.settings.logo') }}" enctype="multipart/form-data" class="hg-brand-form">
                @csrf
                <div class="hg-field"><label for="brand-logo">Upload New Logo</label><input id="brand-logo" name="logo" type="file" accept="image/png,image/jpeg,image/svg+xml,image/webp" required><small class="hg-muted">PNG, JPG, JPEG, SVG or WebP. Maximum 5 MB.</small></div>
                <button class="hg-btn hg-btn-primary" type="submit">Update Logo</button>
            </form>
        </section>
        <section class="hg-card hg-brand-settings-card">
            <h2 class="hg-card-title">Company Favicon</h2>
            <div class="hg-brand-preview favicon">@if($brand['favicon_url'])<img src="{{ $brand['favicon_url'] }}" alt="Company favicon">@else<div class="hg-brand-fallback">🌐<br><small>No favicon</small></div>@endif</div>
            <form method="POST" action="{{ route('system.settings.favicon') }}" enctype="multipart/form-data" class="hg-brand-form">
                @csrf
                <div class="hg-field"><label for="brand-favicon">Upload New Favicon</label><input id="brand-favicon" name="favicon" type="file" accept=".ico,image/x-icon,image/png,image/jpeg,image/webp" required><small class="hg-muted">Square ICO, PNG, JPG, JPEG or WebP. Maximum 1 MB.</small></div>
                <button class="hg-btn hg-btn-primary" type="submit">Update Favicon</button>
            </form>
        </section>
    </div>
</x-layouts::accounting>
