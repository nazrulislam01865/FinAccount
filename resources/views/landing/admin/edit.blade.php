@extends('layouts.landing-admin')

@section('title', 'Landing Page Editor | HisebGhor')

@push('styles')
<style>
    .landing-admin-grid{display:grid;grid-template-columns:minmax(0,1fr)340px;gap:22px;align-items:start}
    .landing-editor-layout{display:grid;grid-template-columns:minmax(0,1fr);gap:18px;align-items:start}
    .landing-section-menu{display:none}
    .landing-section-menu-head{padding:16px 18px;border-bottom:1px solid #eef2f7;background:linear-gradient(180deg,#fff,#fbfcfd)}
    .landing-section-menu-head h3{margin:0;font-size:16px;color:#101828}.landing-section-menu-head p{margin:5px 0 0;color:#667085;font-size:12px;line-height:1.45}
    .landing-section-list{display:grid;padding:10px;gap:6px}.landing-section-link{display:flex;align-items:center;gap:10px;width:100%;border:0;background:transparent;border-radius:14px;padding:11px 12px;text-align:left;font:inherit;font-weight:850;color:#475467;cursor:pointer;transition:.16s ease}.landing-section-link:hover{background:#f0fdf4;color:#087a52}.landing-section-link.is-active{background:#00a86b;color:#fff;box-shadow:0 10px 22px rgba(0,168,107,.18)}.section-count{margin-left:auto;min-width:24px;height:24px;border-radius:999px;background:#f2f4f7;color:#475467;display:inline-grid;place-items:center;font-size:12px;font-weight:900}.landing-section-link.is-active .section-count{background:rgba(255,255,255,.2);color:#fff}
    .landing-form{display:grid;gap:18px}.landing-section-panel{display:none}.landing-section-panel.is-active{display:block}.landing-card{background:#fff;border:1px solid #e5e7eb;border-radius:22px;box-shadow:0 10px 28px rgba(16,24,40,.05);overflow:hidden}
    .landing-card-head{display:flex;align-items:flex-start;justify-content:space-between;gap:16px;padding:18px 20px;border-bottom:1px solid #eef2f7;background:linear-gradient(180deg,#fff,#fbfcfd)}
    .landing-card-head h3{margin:0;font-size:18px;color:#101828}.landing-card-head p{margin:6px 0 0;color:#667085;font-size:13px;line-height:1.55}
    .landing-card-body{padding:20px}.landing-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:14px}.landing-grid.three{grid-template-columns:repeat(3,minmax(0,1fr))}.landing-grid .full{grid-column:1/-1}
    .landing-admin-grid label{display:block;font-weight:850;font-size:13px;color:#344054;margin-bottom:7px}.landing-admin-grid input,.landing-admin-grid textarea,.landing-admin-grid select{width:100%;border:1px solid #d8dee9;border-radius:14px;padding:12px 13px;background:#fff;font:inherit;color:#101828}.landing-admin-grid textarea{min-height:92px;resize:vertical}.landing-admin-grid textarea.tall{min-height:150px}.landing-admin-grid small,.landing-admin-grid .hint{color:#667085;font-size:12px;line-height:1.45}.required{color:#dc2626}.section-toggle{display:flex;align-items:center;gap:8px;font-weight:900;color:#344054;white-space:nowrap}.section-toggle input{width:auto}
    .repeat-list{display:grid;gap:14px}.repeat-card{border:1px solid #e5e7eb;border-radius:18px;background:#fbfcfd;padding:16px;transition:box-shadow .2s ease,border-color .2s ease,background .2s ease}.repeat-card-head{display:flex;justify-content:space-between;gap:14px;align-items:center;margin-bottom:14px}.repeat-card-title{font-weight:900;color:#101828}.new-repeat-card-highlight{border-color:#00a86b!important;background:#f0fdf4!important;box-shadow:0 0 0 4px rgba(0,168,107,.12),0 14px 30px rgba(16,24,40,.08)}
    .landing-validation-summary{display:none;padding:14px 18px;margin-bottom:18px;border:1px solid #fecaca;background:#fef2f2;color:#b42318;border-radius:18px;font-weight:750}.landing-validation-summary.show{display:block}.landing-validation-summary ul{margin:8px 0 0;padding-left:20px}.landing-validation-invalid{border-color:#dc2626!important;background:#fff7f7!important;box-shadow:0 0 0 3px rgba(220,38,38,.10)!important}.landing-validation-error{display:block;margin-top:6px;color:#b42318;font-size:12px;font-weight:800}.required-auto{color:#dc2626;font-weight:900;margin-left:3px}.repeat-list.landing-validation-invalid-list{border:1px dashed #dc2626;border-radius:18px;padding:10px;background:#fff7f7}
    .button-row{display:flex;gap:10px;flex-wrap:wrap;align-items:center}.btn-small{padding:9px 12px!important;border-radius:999px!important;font-size:13px!important}.danger-link{border:1px solid #fecaca!important;background:#fff!important;color:#b42318!important}.muted-divider{height:1px;background:#eef2f7;margin:18px 0}.right-stack{display:grid;gap:18px;position:sticky;top:90px}.form-actions.sticky-actions{position:sticky;bottom:0;background:rgba(255,255,255,.95);backdrop-filter:blur(12px);border:1px solid #e5e7eb;border-radius:18px;padding:14px;z-index:3}.code-help{background:#f8fafc;border:1px dashed #d8dee9;border-radius:14px;padding:12px;color:#475467;font-size:12px;line-height:1.55}
    .image-admin-field{border:1px dashed #cbd5e1;border-radius:18px;background:#f8fafc;padding:14px}.image-admin-preview{margin-top:12px;max-width:100%;max-height:210px;border-radius:16px;border:1px solid #e5e7eb;background:#fff;object-fit:contain;display:block}.screen-image-admin-preview{width:100%;max-height:none;aspect-ratio:16/9;background:#252c40}.image-name-display{margin-top:8px;background:#f9fafb!important;color:#475467!important}.image-admin-field input[type=file]{padding:10px;background:#fff}.image-admin-field .hint{display:block;margin-top:7px}.brand-logo-admin-preview{max-height:120px;object-fit:contain;background:#fff;padding:12px}.brand-logo-upload-only{background:#f8fafc}
    .package-admin-card{padding:18px}.package-fee-admin-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:12px;margin-top:16px}.package-fee-admin-card{border:1px solid #dfe5ec;background:#fff;border-radius:16px;padding:14px}.package-fee-admin-card>strong{display:block;margin-bottom:12px;color:#087a52;font-size:14px}.package-fee-admin-card .landing-grid{grid-template-columns:1fr;gap:10px}.package-fee-admin-card .landing-grid .full{grid-column:auto}
    @media(max-width:1250px){.landing-admin-grid{grid-template-columns:1fr}.right-stack{position:static}.landing-editor-layout{grid-template-columns:1fr}.package-fee-admin-grid{grid-template-columns:1fr}}
    @media(max-width:900px){.landing-editor-layout{grid-template-columns:1fr}.landing-section-menu{position:static}.landing-section-list{grid-template-columns:repeat(2,minmax(0,1fr))}.landing-grid,.landing-grid.three{grid-template-columns:1fr}}
    @media(max-width:640px){.landing-section-list{grid-template-columns:1fr}}
</style>
@endpush

@section('content')
@php
    $value = fn (string $path, $default = '') => old($path, data_get($landing, $path, $default));
    $trans = fn (string $path, string $lang = 'bn', $default = '') => old($path.'.'.$lang, data_get($landing, $path.'.'.$lang, $default));
    $items = fn (string $path, $default = []) => old($path, data_get($landing, $path, $default)) ?: [];
    $lines = function ($rows, string $lang): string {
        $output = [];
        foreach (($rows ?: []) as $row) {
            $output[] = is_array($row) ? (string) ($row[$lang] ?? '') : (string) $row;
        }
        return implode(PHP_EOL, $output);
    };
    $imagePath = function ($row): string {
        if (is_string($row)) {
            return trim($row);
        }

        return trim((string) (
            data_get($row, 'path')
            ?: data_get($row, 'image.path')
            ?: data_get($row, 'image_path')
            ?: ''
        ));
    };
    $imageName = function ($row) use ($imagePath): string {
        $name = trim((string) (
            data_get($row, 'name')
            ?: data_get($row, 'image.name')
            ?: data_get($row, 'image_name')
            ?: ''
        ));
        $path = $imagePath($row);

        return $name !== '' ? $name : ($path !== '' ? basename($path) : '');
    };
    $imageUrl = function (?string $path): string {
        $path = trim((string) $path);
        if ($path === '') {
            return '';
        }
        if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
            return $path;
        }

        $relativePath = ltrim($path, '/');
        $url = asset($relativePath);
        $fullPath = public_path($relativePath);

        return is_file($fullPath) ? $url.'?v='.filemtime($fullPath) : $url;
    };

    $brandLogo = data_get($landing, 'brand.logo', []);
    $brandLogoPath = old('brand.logo.image_path', $imagePath($brandLogo));
    $brandLogoName = old('brand.logo.image_name', $imageName($brandLogo));

    $navLinks = $items('nav_links');
    $heroButtons = $items('hero.buttons');
    $trustItems = $items('trust_items');
    $dashboardStats = $items('hero.dashboard.stats');
    $dashboardRows = $items('hero.dashboard.rows');
    $whyCards = $items('why_cards');
    $screens = $items('screens');
    $audiences = $items('audiences');
    $packages = $items('packages');
    $pricingNotes = $items('pricing_notes');
    $testimonials = $items('testimonials');
    $faqs = $items('faqs');
    $landingSectionKeys = ['basic','nav','hero','why','features','audience','pricing','testimonials','faq','contact','footer'];
    $requestedSection = old('active_section', request('section', 'basic'));
    $activeSection = in_array($requestedSection, $landingSectionKeys, true) ? $requestedSection : 'basic';
@endphp

<div class="page-title">
    <div>
        <span class="page-label">Landing Page</span>
        <h2>HisebGhor Landing Page Control</h2>
        <p>Admin-controlled cards for every public landing-page section. Use the dedicated Landing Admin dashboard menu to open each section, then save without touching accounting logic.</p>
        @if($updatedAt)
            <p class="hint" style="margin-top:6px">Last updated {{ $updatedAt->format('d M Y h:i A') }}@if($updatedBy) by {{ $updatedBy->name }}@endif.</p>
        @endif
    </div>
    <div class="actions" style="border-top:0;padding-top:0">
        <a href="{{ route('landing.show', ['preview' => 1]) }}" target="_blank" class="button btn-ghost">Preview</a>
        <a href="{{ route('landing.public') }}" target="_blank" class="button btn-outline">Open Public Page</a>
    </div>
</div>

@if(session('status'))
    <div class="card" style="padding:14px 18px;margin-bottom:18px;border-color:#bbf7d0;background:#f0fdf4;color:#067647;font-weight:850">
        {{ session('status') }}
    </div>
@endif

@if($errors->any())
    <div class="card" style="padding:14px 18px;margin-bottom:18px;border-color:#fecaca;background:#fef2f2;color:#b42318;font-weight:750">
        <strong>Please fix the following:</strong>
        <ul style="margin:8px 0 0;padding-left:20px">
            @foreach($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif

<div id="landingClientValidationSummary" class="landing-validation-summary" role="alert" aria-live="polite"></div>

<div class="landing-admin-grid">
    <div class="landing-editor-layout">
        <div class="landing-editor-main">
        <form method="POST" action="{{ route('landing-admin.update', ['section' => $activeSection]) }}" class="landing-form" data-frontend-form enctype="multipart/form-data" novalidate>
            @csrf
            @method('PUT')
            <input type="hidden" name="active_section" id="landingActiveSection" value="{{ $activeSection }}">

            <section class="landing-card landing-section-panel {{ $activeSection === 'basic' ? 'is-active' : '' }}" id="basic" data-section-panel="basic">
                <div class="landing-card-head">
                    <div><h3>Basic, Brand & Theme</h3><p>Control publish status, SEO title, brand logo image, language and public theme colors.</p></div>
                </div>
                <div class="landing-card-body landing-grid">
                    <div>
                        <label>Published Status</label>
                        <select name="is_published">
                            <option value="1" @selected(old('is_published', $isPublished ? '1' : '0') === '1')>Published</option>
                            <option value="0" @selected(old('is_published', $isPublished ? '1' : '0') === '0')>Unpublished / Preview only</option>
                        </select>
                    </div>
                    <div>
                        <label>Default Language</label>
                        <select name="meta[default_lang]">
                            <option value="bn" @selected($value('meta.default_lang', 'bn') === 'bn')>Bangla first</option>
                            <option value="en" @selected($value('meta.default_lang', 'bn') === 'en')>English first</option>
                        </select>
                    </div>
                    <div class="full"><label>Browser Title <span class="required">*</span></label><input name="meta[title]" value="{{ $value('meta.title') }}" required></div>
                    <div class="full"><label>Meta Description</label><textarea name="meta[description]">{{ $value('meta.description') }}</textarea></div>
                    <div class="full image-admin-field brand-logo-upload-only">
                        <label>
                            Brand Logo Image
                            <small>Upload the complete logo artwork. This one image is used in both header and footer. PNG, JPG, WEBP or GIF; max 4 MB</small>
                        </label>
                        <input type="file" name="brand[logo][image]" accept="image/*" data-optional="true" data-file-input>
                        <input type="hidden" name="brand[logo][image_path]" value="{{ $brandLogoPath }}">
                        <input type="hidden" name="brand[logo][image_name]" value="{{ $brandLogoName }}">
                        <input type="text" class="image-name-display" value="{{ $brandLogoName }}" placeholder="No brand logo image uploaded yet" readonly data-file-name-display data-current-name="{{ $brandLogoName }}" data-optional="true">
                        <span class="hint">Upload the whole brand lockup as one image, for example icon + wordmark + small slogan. This single image will render in the public header and footer.</span>
                        @if($brandLogoPath !== '')
                            <img src="{{ $imageUrl($brandLogoPath) }}" alt="{{ $brandLogoName ?: 'Landing brand logo' }}" class="image-admin-preview brand-logo-admin-preview">
                        @endif
                    </div>
                    <div><label>Primary Green</label><input name="theme[green]" value="{{ $value('theme.green', '#00a86b') }}"></div>
                    <div><label>Dark Green</label><input name="theme[green_dark]" value="{{ $value('theme.green_dark', '#087a52') }}"></div>
                    <div><label>Soft Green</label><input name="theme[green_soft]" value="{{ $value('theme.green_soft', '#e9fff5') }}"></div>
                    <div><label>Blue Accent</label><input name="theme[blue]" value="{{ $value('theme.blue', '#2563eb') }}"></div>
                    <div><label>Gold Accent</label><input name="theme[gold]" value="{{ $value('theme.gold', '#f59e0b') }}"></div>
                    <div><label>Background</label><input name="theme[bg]" value="{{ $value('theme.bg', '#f8fafc') }}"></div>
                    <div><label>Text Color</label><input name="theme[ink]" value="{{ $value('theme.ink', '#101828') }}"></div>
                    <div><label>Muted Text</label><input name="theme[muted]" value="{{ $value('theme.muted', '#667085') }}"></div>
                </div>
            </section>

            <section class="landing-card landing-section-panel {{ $activeSection === 'nav' ? 'is-active' : '' }}" id="nav" data-section-panel="nav">
                <div class="landing-card-head">
                    <div><h3>Landing Navigation</h3><p>Add or edit the public landing-page menu items and the main call-to-action button. Login access is intentionally not displayed in the public header.</p></div>
                    <button type="button" class="button btn-outline btn-small" data-add="nav_links">Add Menu</button>
                </div>
                <div class="landing-card-body">
                    <div class="landing-grid">
                        <div><label>Demo Button Text Bangla <span class="required">*</span></label><input name="cta[primary][label][bn]" value="{{ $trans('cta.primary.label', 'bn') }}" required></div>
                        <div><label>Demo Button Text English <span class="required">*</span></label><input name="cta[primary][label][en]" value="{{ $trans('cta.primary.label', 'en') }}" required></div>
                        <div class="full"><label>Demo Button Link <span class="required">*</span></label><input name="cta[primary][href]" value="{{ $value('cta.primary.href', '#contact') }}" placeholder="#contact, https://wa.me/880..., or any URL" required></div>
                        <div class="full"><div class="code-help">This controls the top-right public CTA button text and destination. Use #contact to scroll to the demo form, or paste a WhatsApp/email/website link.</div></div>
                    </div>
                    <div class="muted-divider"></div>
                    <div class="repeat-list" data-repeater="nav_links">
                        @foreach(array_values($navLinks) as $index => $link)
                            <div class="repeat-card" data-repeat-item>
                                <div class="repeat-card-head"><div class="repeat-card-title">Menu Item</div><button type="button" class="button btn-ghost btn-small danger-link" data-remove-card>Remove</button></div>
                                <div class="landing-grid three">
                                    <div><label>Bangla Label</label><input name="nav_links[{{ $index }}][label][bn]" value="{{ data_get($link, 'label.bn') }}"></div>
                                    <div><label>English Label</label><input name="nav_links[{{ $index }}][label][en]" value="{{ data_get($link, 'label.en') }}"></div>
                                    <div><label>Link / Section ID</label><input name="nav_links[{{ $index }}][href]" value="{{ data_get($link, 'href', '#') }}"></div>
                                </div>
                            </div>
                        @endforeach
                    </div>                </div>
            </section>

            <section class="landing-card landing-section-panel {{ $activeSection === 'hero' ? 'is-active' : '' }}" id="hero" data-section-panel="hero">
                <div class="landing-card-head">
                    <div><h3>Hero Section</h3><p>Top headline, buttons, trust labels and live dashboard preview.</p></div>
                    <label class="section-toggle"><input type="hidden" name="hero[enabled]" value="0"><input type="checkbox" name="hero[enabled]" value="1" @checked($value('hero.enabled', true))> Enabled</label>
                </div>
                <div class="landing-card-body">
                    <div class="landing-grid">
                        <div><label>Eyebrow Bangla</label><input name="hero[eyebrow][bn]" value="{{ $trans('hero.eyebrow', 'bn') }}"></div>
                        <div><label>Eyebrow English</label><input name="hero[eyebrow][en]" value="{{ $trans('hero.eyebrow', 'en') }}"></div>
                        <div class="full"><label>Hero Title Bangla <span class="required">*</span></label><input name="hero[title][bn]" value="{{ $trans('hero.title', 'bn') }}" required></div>
                        <div class="full"><label>Hero Title English <span class="required">*</span></label><input name="hero[title][en]" value="{{ $trans('hero.title', 'en') }}" required></div>
                        <div><label>Hero Subtitle Bangla</label><textarea name="hero[subtitle][bn]" class="tall">{{ $trans('hero.subtitle', 'bn') }}</textarea></div>
                        <div><label>Hero Subtitle English</label><textarea name="hero[subtitle][en]" class="tall">{{ $trans('hero.subtitle', 'en') }}</textarea></div>
                    </div>
                    <div class="muted-divider"></div>
                    <div class="landing-card-head" style="padding:0 0 14px;border:0;background:transparent"><div><h3>Hero Buttons</h3><p>Admin can add more buttons or links.</p></div><button type="button" class="button btn-outline btn-small" data-add="hero_buttons">Add Button</button></div>
                    <div class="repeat-list" data-repeater="hero_buttons">
                        @foreach(array_values($heroButtons) as $index => $button)
                            <div class="repeat-card" data-repeat-item>
                                <div class="repeat-card-head"><div class="repeat-card-title">Hero Button</div><button type="button" class="button btn-ghost btn-small danger-link" data-remove-card>Remove</button></div>
                                <div class="landing-grid three">
                                    <div><label>Bangla Label</label><input name="hero[buttons][{{ $index }}][label][bn]" value="{{ data_get($button, 'label.bn') }}"></div>
                                    <div><label>English Label</label><input name="hero[buttons][{{ $index }}][label][en]" value="{{ data_get($button, 'label.en') }}"></div>
                                    <div><label>Style</label><select name="hero[buttons][{{ $index }}][style]"><option value="primary" @selected(data_get($button, 'style') === 'primary')>Primary</option><option value="outline" @selected(data_get($button, 'style') === 'outline')>Outline</option><option value="dark" @selected(data_get($button, 'style') === 'dark')>Dark</option></select></div>
                                    <div class="full"><label>Link</label><input name="hero[buttons][{{ $index }}][href]" value="{{ data_get($button, 'href', '#contact') }}"></div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                    <div class="muted-divider"></div>
                    <div class="landing-card-head" style="padding:0 0 14px;border:0;background:transparent"><div><h3>Trust Items</h3><p>Small check-mark labels below the hero button group.</p></div><button type="button" class="button btn-outline btn-small" data-add="trust_items">Add Trust Item</button></div>
                    <div class="repeat-list" data-repeater="trust_items">
                        @foreach(array_values($trustItems) as $index => $trust)
                            <div class="repeat-card" data-repeat-item>
                                <div class="repeat-card-head"><div class="repeat-card-title">Trust Item</div><button type="button" class="button btn-ghost btn-small danger-link" data-remove-card>Remove</button></div>
                                <div class="landing-grid"><div><label>Bangla</label><input name="trust_items[{{ $index }}][bn]" value="{{ data_get($trust, 'bn') }}"></div><div><label>English</label><input name="trust_items[{{ $index }}][en]" value="{{ data_get($trust, 'en') }}"></div></div>
                            </div>
                        @endforeach
                    </div>
                    <div class="muted-divider"></div>
                    <h3 class="section-title">Dashboard Preview</h3>
                    @php
                        $heroDashboard = data_get($landing, 'hero.dashboard', []);
                        $heroImagePath = old('hero.dashboard.image_path', $imagePath($heroDashboard));
                        $heroImageName = old('hero.dashboard.image_name', $imageName($heroDashboard));
                    @endphp
                    <div class="landing-grid">
                        <div class="full image-admin-field">
                            <label>Dashboard Preview Image <small>PNG, JPG, WEBP or GIF; max 4 MB</small></label>
                            <input type="file" name="hero[dashboard][image]" accept="image/*" data-optional="true" data-file-input>
                            <input type="hidden" name="hero[dashboard][image_path]" value="{{ $heroImagePath }}">
                            <input type="hidden" name="hero[dashboard][image_name]" value="{{ $heroImageName }}">
                            <input type="text" class="image-name-display" value="{{ $heroImageName }}" placeholder="No dashboard preview image uploaded yet" readonly data-file-name-display data-current-name="{{ $heroImageName }}" data-optional="true">
                            <span class="hint">Upload the full dashboard preview as one image. No separate dashboard title, subtitle, status chip, or mock data rows will be shown.</span>
                            @if($heroImagePath !== '')
                                <img src="{{ $imageUrl($heroImagePath) }}" alt="{{ $heroImageName ?: 'Dashboard preview image' }}" class="image-admin-preview">
                            @endif
                        </div>
                    </div>
                </div>
            </section>

            <section class="landing-card landing-section-panel {{ $activeSection === 'why' ? 'is-active' : '' }}" id="why" data-section-panel="why">
                <div class="landing-card-head"><div><h3>Why HisebGhor Section</h3><p>Section title and the feature cards below it.</p></div><label class="section-toggle"><input type="hidden" name="why[enabled]" value="0"><input type="checkbox" name="why[enabled]" value="1" @checked($value('why.enabled', true))> Enabled</label></div>
                <div class="landing-card-body">
                    <div class="landing-grid">
                        <div><label>Mini Bangla</label><input name="why[mini][bn]" value="{{ $trans('why.mini', 'bn') }}"></div><div><label>Mini English</label><input name="why[mini][en]" value="{{ $trans('why.mini', 'en') }}"></div>
                        <div><label>Title Bangla</label><textarea name="why[title][bn]">{{ $trans('why.title', 'bn') }}</textarea></div><div><label>Title English</label><textarea name="why[title][en]">{{ $trans('why.title', 'en') }}</textarea></div>
                        <div><label>Subtitle Bangla</label><textarea name="why[subtitle][bn]">{{ $trans('why.subtitle', 'bn') }}</textarea></div><div><label>Subtitle English</label><textarea name="why[subtitle][en]">{{ $trans('why.subtitle', 'en') }}</textarea></div>
                    </div>
                    <div class="muted-divider"></div>
                    <div class="landing-card-head" style="padding:0 0 14px;border:0;background:transparent"><div><h3>Why Cards</h3></div><button type="button" class="button btn-outline btn-small" data-add="why_cards">Add Card</button></div>
                    <div class="repeat-list" data-repeater="why_cards">
                        @foreach(array_values($whyCards) as $index => $card)
                            <div class="repeat-card" data-repeat-item><div class="repeat-card-head"><div class="repeat-card-title">Feature Card</div><button type="button" class="button btn-ghost btn-small danger-link" data-remove-card>Remove</button></div><div class="landing-grid"><div><label>Icon</label><input name="why_cards[{{ $index }}][icon]" value="{{ data_get($card, 'icon', '✓') }}"></div><div></div><div><label>Title Bangla</label><input name="why_cards[{{ $index }}][title][bn]" value="{{ data_get($card, 'title.bn') }}"></div><div><label>Title English</label><input name="why_cards[{{ $index }}][title][en]" value="{{ data_get($card, 'title.en') }}"></div><div><label>Body Bangla</label><textarea name="why_cards[{{ $index }}][body][bn]">{{ data_get($card, 'body.bn') }}</textarea></div><div><label>Body English</label><textarea name="why_cards[{{ $index }}][body][en]">{{ data_get($card, 'body.en') }}</textarea></div></div></div>
                        @endforeach
                    </div>
                </div>
            </section>

            <section class="landing-card landing-section-panel {{ $activeSection === 'features' ? 'is-active' : '' }}" id="features" data-section-panel="features">
                <div class="landing-card-head"><div><h3>Screenshots & Feature Screens</h3><p>Upload an image in any dimension for every feature card. Each image is automatically fitted inside the fixed 16:9 landing-page frame without stretching.</p></div><label class="section-toggle"><input type="hidden" name="features[enabled]" value="0"><input type="checkbox" name="features[enabled]" value="1" @checked($value('features.enabled', true))> Enabled</label></div>
                <div class="landing-card-body">
                    <div class="landing-grid"><div><label>Mini Bangla</label><input name="features[mini][bn]" value="{{ $trans('features.mini', 'bn') }}"></div><div><label>Mini English</label><input name="features[mini][en]" value="{{ $trans('features.mini', 'en') }}"></div><div><label>Title Bangla</label><textarea name="features[title][bn]">{{ $trans('features.title', 'bn') }}</textarea></div><div><label>Title English</label><textarea name="features[title][en]">{{ $trans('features.title', 'en') }}</textarea></div><div><label>Subtitle Bangla</label><textarea name="features[subtitle][bn]">{{ $trans('features.subtitle', 'bn') }}</textarea></div><div><label>Subtitle English</label><textarea name="features[subtitle][en]">{{ $trans('features.subtitle', 'en') }}</textarea></div></div>
                    <div class="muted-divider"></div>
                    <div class="landing-card-head" style="padding:0 0 14px;border:0;background:transparent"><div><h3>Screen Cards</h3></div><button type="button" class="button btn-outline btn-small" data-add="screens">Add Screen</button></div>
                    <div class="repeat-list" data-repeater="screens">
                        @foreach(array_values($screens) as $index => $screen)
                            @php
                                $screenImagePath = old('screens.'.$index.'.image_path', $imagePath($screen));
                                $screenImageName = old('screens.'.$index.'.image_name', $imageName($screen));
                            @endphp
                            <div class="repeat-card" data-repeat-item>
                                <div class="repeat-card-head"><div class="repeat-card-title">Screen Card</div><button type="button" class="button btn-ghost btn-small danger-link" data-remove-card>Remove</button></div>
                                <div class="landing-grid">
                                    <div class="full image-admin-field">
                                        <label>Feature Image <small>PNG, JPG, WEBP or GIF; any image ratio; max 4 MB</small></label>
                                        <input type="file" name="screens[{{ $index }}][image]" accept="image/*" data-optional="true" data-file-input data-screen-image>
                                        <input type="hidden" name="screens[{{ $index }}][image_path]" value="{{ $screenImagePath }}">
                                        <input type="hidden" name="screens[{{ $index }}][image_name]" value="{{ $screenImageName }}">
                                        <input type="text" class="image-name-display" value="{{ $screenImageName }}" placeholder="No white preview image uploaded for this card" readonly data-file-name-display data-current-name="{{ $screenImageName }}" data-optional="true">
                                        <span class="hint">Upload any portrait, square, or landscape image. It will automatically fit inside a fixed 16:9 frame on the landing page without distortion.</span>
                                        @if($screenImagePath !== '')
                                            <img src="{{ $imageUrl($screenImagePath) }}" alt="{{ $screenImageName ?: 'Feature screen image' }}" class="image-admin-preview screen-image-admin-preview">
                                        @endif
                                    </div>
                                    <div><label>Title Bangla</label><input name="screens[{{ $index }}][title][bn]" value="{{ data_get($screen, 'title.bn') }}"></div>
                                    <div><label>Title English</label><input name="screens[{{ $index }}][title][en]" value="{{ data_get($screen, 'title.en') }}"></div>
                                    <div><label>Body Bangla</label><textarea name="screens[{{ $index }}][body][bn]">{{ data_get($screen, 'body.bn') }}</textarea></div>
                                    <div><label>Body English</label><textarea name="screens[{{ $index }}][body][en]">{{ data_get($screen, 'body.en') }}</textarea></div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            </section>

            <section class="landing-card landing-section-panel {{ $activeSection === 'audience' ? 'is-active' : '' }}" id="audience" data-section-panel="audience">
                <div class="landing-card-head"><div><h3>Business Suitability Section</h3><p>Manage the business-type section displayed immediately before pricing.</p></div><label class="section-toggle"><input type="hidden" name="audience[enabled]" value="0"><input type="checkbox" name="audience[enabled]" value="1" @checked($value('audience.enabled', true))> Enabled</label></div>
                <div class="landing-card-body">
                    <div class="landing-grid"><div><label>Icon</label><input name="audience[icon]" value="{{ $value('audience.icon', '🏪') }}"></div><div></div><div><label>Title Bangla</label><input name="audience[title][bn]" value="{{ $trans('audience.title', 'bn') }}"></div><div><label>Title English</label><input name="audience[title][en]" value="{{ $trans('audience.title', 'en') }}"></div><div><label>Body Bangla</label><textarea name="audience[body][bn]">{{ $trans('audience.body', 'bn') }}</textarea></div><div><label>Body English</label><textarea name="audience[body][en]">{{ $trans('audience.body', 'en') }}</textarea></div></div>
                    <div class="muted-divider"></div>
                    <div class="landing-card-head" style="padding:0 0 14px;border:0;background:transparent"><div><h3>Business Type Cards</h3></div><button type="button" class="button btn-outline btn-small" data-add="audiences">Add Business Type</button></div>
                    <div class="repeat-list" data-repeater="audiences">
                        @foreach(array_values($audiences) as $index => $audience)
                            <div class="repeat-card" data-repeat-item><div class="repeat-card-head"><div class="repeat-card-title">Business Type Card</div><button type="button" class="button btn-ghost btn-small danger-link" data-remove-card>Remove</button></div><div class="landing-grid"><div><label>Title Bangla</label><input name="audiences[{{ $index }}][title][bn]" value="{{ data_get($audience, 'title.bn') }}"></div><div><label>Title English</label><input name="audiences[{{ $index }}][title][en]" value="{{ data_get($audience, 'title.en') }}"></div><div><label>Body Bangla</label><textarea name="audiences[{{ $index }}][body][bn]">{{ data_get($audience, 'body.bn') }}</textarea></div><div><label>Body English</label><textarea name="audiences[{{ $index }}][body][en]">{{ data_get($audience, 'body.en') }}</textarea></div></div></div>
                        @endforeach
                    </div>
                </div>
            </section>

            <section class="landing-card landing-section-panel {{ $activeSection === 'pricing' ? 'is-active' : '' }}" id="pricing" data-section-panel="pricing">
                <div class="landing-card-head">
                    <div>
                        <h3>Implementation Packages & Pricing</h3>
                        <p>Manage the three fee rows, package features, recommended state, and important-note cards shown on the landing page.</p>
                    </div>
                    <label class="section-toggle"><input type="hidden" name="pricing[enabled]" value="0"><input type="checkbox" name="pricing[enabled]" value="1" @checked($value('pricing.enabled', true))> Enabled</label>
                </div>
                <div class="landing-card-body">
                    <div class="landing-grid">
                        <div><label>Mini Bangla</label><input name="pricing[mini][bn]" value="{{ $trans('pricing.mini', 'bn') }}"></div>
                        <div><label>Mini English</label><input name="pricing[mini][en]" value="{{ $trans('pricing.mini', 'en') }}"></div>
                        <div><label>Title Bangla</label><textarea name="pricing[title][bn]">{{ $trans('pricing.title', 'bn') }}</textarea></div>
                        <div><label>Title English</label><textarea name="pricing[title][en]">{{ $trans('pricing.title', 'en') }}</textarea></div>
                        <div><label>Subtitle Bangla</label><textarea name="pricing[subtitle][bn]">{{ $trans('pricing.subtitle', 'bn') }}</textarea></div>
                        <div><label>Subtitle English</label><textarea name="pricing[subtitle][en]">{{ $trans('pricing.subtitle', 'en') }}</textarea></div>
                        <div><label>Important Notes Heading Bangla</label><input name="pricing[notes_title][bn]" value="{{ $trans('pricing.notes_title', 'bn', 'Important Notes') }}"></div>
                        <div><label>Important Notes Heading English</label><input name="pricing[notes_title][en]" value="{{ $trans('pricing.notes_title', 'en', 'Important Notes') }}"></div>
                    </div>

                    <div class="muted-divider"></div>

                    <div class="landing-card-head" style="padding:0 0 14px;border:0;background:transparent">
                        <div>
                            <h3>Package Cards</h3>
                            <p>Set installation, monthly maintenance, server hosting, and one feature per line in both languages.</p>
                        </div>
                        <button type="button" class="button btn-outline btn-small" data-add="packages">Add Package</button>
                    </div>

                    <div class="repeat-list" data-repeater="packages">
                        @foreach(array_values($packages) as $index => $package)
                            @php
                                $pkgFeaturesBn = data_get($package, 'features_bn', $lines(data_get($package, 'features', []), 'bn'));
                                $pkgFeaturesEn = data_get($package, 'features_en', $lines(data_get($package, 'features', []), 'en'));
                            @endphp
                            <div class="repeat-card package-admin-card" data-repeat-item>
                                <div class="repeat-card-head">
                                    <div class="repeat-card-title">Implementation Package</div>
                                    <button type="button" class="button btn-ghost btn-small danger-link" data-remove-card>Remove</button>
                                </div>

                                <div class="landing-grid three">
                                    <div>
                                        <label>Package Icon</label>
                                        <select name="packages[{{ $index }}][icon]">
                                            <option value="cloud" @selected(data_get($package, 'icon', 'cloud') === 'cloud')>Cloud</option>
                                            <option value="building" @selected(data_get($package, 'icon') === 'building')>Standard / Building</option>
                                            <option value="server" @selected(data_get($package, 'icon') === 'server')>Server</option>
                                        </select>
                                    </div>
                                    <div><label>Name Bangla</label><input name="packages[{{ $index }}][name][bn]" value="{{ data_get($package, 'name.bn') }}"></div>
                                    <div><label>Name English</label><input name="packages[{{ $index }}][name][en]" value="{{ data_get($package, 'name.en') }}"></div>
                                    <div>
                                        <label>Recommended Package?</label>
                                        <select name="packages[{{ $index }}][popular]">
                                            <option value="0" @selected(!data_get($package, 'popular'))>No</option>
                                            <option value="1" @selected(data_get($package, 'popular'))>Yes</option>
                                        </select>
                                    </div>
                                    <div><label>Top Ribbon Bangla</label><input name="packages[{{ $index }}][popular_label][bn]" value="{{ data_get($package, 'popular_label.bn', '★ Recommended') }}"></div>
                                    <div><label>Top Ribbon English</label><input name="packages[{{ $index }}][popular_label][en]" value="{{ data_get($package, 'popular_label.en', '★ Recommended') }}"></div>
                                    <div><label>Small Tag Bangla</label><input name="packages[{{ $index }}][tag][bn]" value="{{ data_get($package, 'tag.bn') }}"></div>
                                    <div><label>Small Tag English</label><input name="packages[{{ $index }}][tag][en]" value="{{ data_get($package, 'tag.en') }}"></div>
                                    <div></div>
                                    <div><label>Business Description Bangla</label><textarea name="packages[{{ $index }}][body][bn]">{{ data_get($package, 'body.bn') }}</textarea></div>
                                    <div><label>Business Description English</label><textarea name="packages[{{ $index }}][body][en]">{{ data_get($package, 'body.en') }}</textarea></div>
                                    <div></div>
                                </div>

                                <div class="package-fee-admin-grid">
                                    @foreach(['installation' => 'Installation Fee', 'maintenance' => 'Maintenance Fee', 'hosting' => 'Server Hosting'] as $feeKey => $feeTitle)
                                        <div class="package-fee-admin-card">
                                            <strong>{{ $feeTitle }}</strong>
                                            <div class="landing-grid">
                                                <div><label>Label Bangla</label><input name="packages[{{ $index }}][fees][{{ $feeKey }}][label][bn]" value="{{ data_get($package, 'fees.'.$feeKey.'.label.bn', $feeTitle) }}"></div>
                                                <div><label>Label English</label><input name="packages[{{ $index }}][fees][{{ $feeKey }}][label][en]" value="{{ data_get($package, 'fees.'.$feeKey.'.label.en', $feeTitle) }}"></div>
                                                <div class="full"><label>Amount / Value</label><input name="packages[{{ $index }}][fees][{{ $feeKey }}][amount]" value="{{ data_get($package, 'fees.'.$feeKey.'.amount') }}" placeholder="Example: ৳35,000 – ৳50,000 or Actual cost"></div>
                                                <div><label>Note Bangla</label><input name="packages[{{ $index }}][fees][{{ $feeKey }}][note][bn]" value="{{ data_get($package, 'fees.'.$feeKey.'.note.bn') }}" placeholder="One-time or /month"></div>
                                                <div><label>Note English</label><input name="packages[{{ $index }}][fees][{{ $feeKey }}][note][en]" value="{{ data_get($package, 'fees.'.$feeKey.'.note.en') }}" placeholder="One-time or /month"></div>
                                            </div>
                                        </div>
                                    @endforeach
                                </div>

                                <div class="landing-grid" style="margin-top:14px">
                                    <div><label>Features Bangla <small>One feature per line</small></label><textarea name="packages[{{ $index }}][features_bn]" class="tall">{{ $pkgFeaturesBn }}</textarea></div>
                                    <div><label>Features English <small>One feature per line</small></label><textarea name="packages[{{ $index }}][features_en]" class="tall">{{ $pkgFeaturesEn }}</textarea></div>
                                </div>
                            </div>
                        @endforeach
                    </div>

                    <div class="muted-divider"></div>

                    <div class="landing-card-head" style="padding:0 0 14px;border:0;background:transparent">
                        <div><h3>Important Note Cards</h3><p>These cards appear below the package comparison.</p></div>
                        <button type="button" class="button btn-outline btn-small" data-add="pricing_notes">Add Note</button>
                    </div>
                    <div class="repeat-list" data-repeater="pricing_notes">
                        @foreach(array_values($pricingNotes) as $index => $note)
                            <div class="repeat-card" data-repeat-item>
                                <div class="repeat-card-head"><div class="repeat-card-title">Important Note</div><button type="button" class="button btn-ghost btn-small danger-link" data-remove-card>Remove</button></div>
                                <div class="landing-grid three">
                                    <div>
                                        <label>Icon</label>
                                        <select name="pricing_notes[{{ $index }}][icon]">
                                            <option value="tag" @selected(data_get($note, 'icon', 'tag') === 'tag')>Pricing Tag</option>
                                            <option value="server" @selected(data_get($note, 'icon') === 'server')>Server</option>
                                            <option value="wrench" @selected(data_get($note, 'icon') === 'wrench')>Maintenance</option>
                                        </select>
                                    </div>
                                    <div><label>Title Bangla</label><input name="pricing_notes[{{ $index }}][title][bn]" value="{{ data_get($note, 'title.bn') }}"></div>
                                    <div><label>Title English</label><input name="pricing_notes[{{ $index }}][title][en]" value="{{ data_get($note, 'title.en') }}"></div>
                                    <div><label>Body Bangla</label><textarea name="pricing_notes[{{ $index }}][body][bn]">{{ data_get($note, 'body.bn') }}</textarea></div>
                                    <div><label>Body English</label><textarea name="pricing_notes[{{ $index }}][body][en]">{{ data_get($note, 'body.en') }}</textarea></div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            </section>

            <section class="landing-card landing-section-panel {{ $activeSection === 'testimonials' ? 'is-active' : '' }}" id="testimonials" data-section-panel="testimonials">
                <div class="landing-card-head"><div><h3>Testimonials</h3><p>Control testimonial section and customer quote cards.</p></div><label class="section-toggle"><input type="hidden" name="testimonials_section[enabled]" value="0"><input type="checkbox" name="testimonials_section[enabled]" value="1" @checked($value('testimonials_section.enabled', true))> Enabled</label></div>
                <div class="landing-card-body">
                    <div class="landing-grid"><div><label>Mini Bangla</label><input name="testimonials_section[mini][bn]" value="{{ $trans('testimonials_section.mini', 'bn') }}"></div><div><label>Mini English</label><input name="testimonials_section[mini][en]" value="{{ $trans('testimonials_section.mini', 'en') }}"></div><div><label>Title Bangla</label><input name="testimonials_section[title][bn]" value="{{ $trans('testimonials_section.title', 'bn') }}"></div><div><label>Title English</label><input name="testimonials_section[title][en]" value="{{ $trans('testimonials_section.title', 'en') }}"></div></div>
                    <div class="muted-divider"></div>
                    <div class="landing-card-head" style="padding:0 0 14px;border:0;background:transparent"><div><h3>Testimonial Cards</h3></div><button type="button" class="button btn-outline btn-small" data-add="testimonials">Add Testimonial</button></div>
                    <div class="repeat-list" data-repeater="testimonials">
                        @foreach(array_values($testimonials) as $index => $testimonial)
                            <div class="repeat-card" data-repeat-item><div class="repeat-card-head"><div class="repeat-card-title">Testimonial</div><button type="button" class="button btn-ghost btn-small danger-link" data-remove-card>Remove</button></div><div class="landing-grid"><div><label>Name</label><input name="testimonials[{{ $index }}][name]" value="{{ data_get($testimonial, 'name') }}"></div><div><label>Avatar Text</label><input name="testimonials[{{ $index }}][avatar]" value="{{ data_get($testimonial, 'avatar') }}"></div><div><label>Role Bangla</label><input name="testimonials[{{ $index }}][role][bn]" value="{{ data_get($testimonial, 'role.bn') }}"></div><div><label>Role English</label><input name="testimonials[{{ $index }}][role][en]" value="{{ data_get($testimonial, 'role.en') }}"></div><div><label>Quote Bangla</label><textarea name="testimonials[{{ $index }}][quote][bn]">{{ data_get($testimonial, 'quote.bn') }}</textarea></div><div><label>Quote English</label><textarea name="testimonials[{{ $index }}][quote][en]">{{ data_get($testimonial, 'quote.en') }}</textarea></div></div></div>
                        @endforeach
                    </div>
                </div>
            </section>

            <section class="landing-card landing-section-panel {{ $activeSection === 'faq' ? 'is-active' : '' }}" id="faq" data-section-panel="faq">
                <div class="landing-card-head"><div><h3>FAQ Section</h3><p>Add as many frequently asked questions as needed.</p></div><label class="section-toggle"><input type="hidden" name="faq_section[enabled]" value="0"><input type="checkbox" name="faq_section[enabled]" value="1" @checked($value('faq_section.enabled', true))> Enabled</label></div>
                <div class="landing-card-body">
                    <div class="landing-grid"><div><label>Mini Bangla</label><input name="faq_section[mini][bn]" value="{{ $trans('faq_section.mini', 'bn') }}"></div><div><label>Mini English</label><input name="faq_section[mini][en]" value="{{ $trans('faq_section.mini', 'en') }}"></div><div><label>Title Bangla</label><input name="faq_section[title][bn]" value="{{ $trans('faq_section.title', 'bn') }}"></div><div><label>Title English</label><input name="faq_section[title][en]" value="{{ $trans('faq_section.title', 'en') }}"></div></div>
                    <div class="muted-divider"></div>
                    <div class="landing-card-head" style="padding:0 0 14px;border:0;background:transparent"><div><h3>FAQ Cards</h3></div><button type="button" class="button btn-outline btn-small" data-add="faqs">Add FAQ</button></div>
                    <div class="repeat-list" data-repeater="faqs">
                        @foreach(array_values($faqs) as $index => $faq)
                            <div class="repeat-card" data-repeat-item><div class="repeat-card-head"><div class="repeat-card-title">FAQ</div><button type="button" class="button btn-ghost btn-small danger-link" data-remove-card>Remove</button></div><div class="landing-grid"><div><label>Question Bangla</label><input name="faqs[{{ $index }}][question][bn]" value="{{ data_get($faq, 'question.bn') }}"></div><div><label>Question English</label><input name="faqs[{{ $index }}][question][en]" value="{{ data_get($faq, 'question.en') }}"></div><div><label>Answer Bangla</label><textarea name="faqs[{{ $index }}][answer][bn]">{{ data_get($faq, 'answer.bn') }}</textarea></div><div><label>Answer English</label><textarea name="faqs[{{ $index }}][answer][en]">{{ data_get($faq, 'answer.en') }}</textarea></div><div class="full"><label class="section-toggle"><input type="hidden" name="faqs[{{ $index }}][open]" value="0"><input type="checkbox" name="faqs[{{ $index }}][open]" value="1" @checked(data_get($faq, 'open'))> Open by default</label></div></div></div>
                        @endforeach
                    </div>
                </div>
            </section>

            <section class="landing-card landing-section-panel {{ $activeSection === 'contact' ? 'is-active' : '' }}" id="contact" data-section-panel="contact">
                <div class="landing-card-head"><div><h3>Demo Request & CAPTCHA</h3><p>Manage the public demo form, contact details, and security-verification popup.</p></div><label class="section-toggle"><input type="hidden" name="contact[enabled]" value="0"><input type="checkbox" name="contact[enabled]" value="1" @checked($value('contact.enabled', true))> Enabled</label></div>
                <div class="landing-card-body landing-grid">
                    <div><label>Title Bangla</label><input name="contact[title][bn]" value="{{ $trans('contact.title', 'bn') }}"></div><div><label>Title English</label><input name="contact[title][en]" value="{{ $trans('contact.title', 'en') }}"></div>
                    <div><label>Body Bangla</label><textarea name="contact[body][bn]">{{ $trans('contact.body', 'bn') }}</textarea></div><div><label>Body English</label><textarea name="contact[body][en]">{{ $trans('contact.body', 'en') }}</textarea></div>
                    <div><label>Phone</label><input name="contact[phone]" value="{{ $value('contact.phone') }}"></div><div><label>Email</label><input type="email" name="contact[email]" value="{{ $value('contact.email') }}"></div>
                    <div><label>Phone Note Bangla</label><input name="contact[phone_note][bn]" value="{{ $trans('contact.phone_note', 'bn') }}"></div><div><label>Phone Note English</label><input name="contact[phone_note][en]" value="{{ $trans('contact.phone_note', 'en') }}"></div>
                    <div><label>Email Note Bangla</label><input name="contact[email_note][bn]" value="{{ $trans('contact.email_note', 'bn') }}"></div><div><label>Email Note English</label><input name="contact[email_note][en]" value="{{ $trans('contact.email_note', 'en') }}"></div>
                    <div class="full"><div class="code-help">These labels control the public contact/demo area. Phone clicks open WhatsApp, and email clicks open the visitor's email app.</div></div>
                    <div><label>Name Placeholder Bangla</label><input name="contact[form][name][bn]" value="{{ $trans('contact.form.name', 'bn') }}"></div><div><label>Name Placeholder English</label><input name="contact[form][name][en]" value="{{ $trans('contact.form.name', 'en') }}"></div>
                    <div><label>Business Placeholder Bangla</label><input name="contact[form][business_name][bn]" value="{{ $trans('contact.form.business_name', 'bn') }}"></div><div><label>Business Placeholder English</label><input name="contact[form][business_name][en]" value="{{ $trans('contact.form.business_name', 'en') }}"></div>
                    <div><label>Mobile Placeholder Bangla</label><input name="contact[form][mobile][bn]" value="{{ $trans('contact.form.mobile', 'bn') }}"></div><div><label>Mobile Placeholder English</label><input name="contact[form][mobile][en]" value="{{ $trans('contact.form.mobile', 'en') }}"></div>
                    <div><label>Email Placeholder Bangla</label><input name="contact[form][email][bn]" value="{{ $trans('contact.form.email', 'bn') }}"></div><div><label>Email Placeholder English</label><input name="contact[form][email][en]" value="{{ $trans('contact.form.email', 'en') }}"></div>
                    <div><label>Message Placeholder Bangla</label><textarea name="contact[form][message][bn]">{{ $trans('contact.form.message', 'bn') }}</textarea></div><div><label>Message Placeholder English</label><textarea name="contact[form][message][en]">{{ $trans('contact.form.message', 'en') }}</textarea></div>
                    <div><label>Submit Button Bangla</label><input name="contact[form][button][bn]" value="{{ $trans('contact.form.button', 'bn') }}"></div><div><label>Submit Button English</label><input name="contact[form][button][en]" value="{{ $trans('contact.form.button', 'en') }}"></div>
                    <div><label>Success Message Bangla</label><input name="contact[form][success][bn]" value="{{ $trans('contact.form.success', 'bn') }}"></div><div><label>Success Message English</label><input name="contact[form][success][en]" value="{{ $trans('contact.form.success', 'en') }}"></div>
                    <div><label>Error Message Bangla</label><input name="contact[form][error][bn]" value="{{ $trans('contact.form.error', 'bn') }}"></div><div><label>Error Message English</label><input name="contact[form][error][en]" value="{{ $trans('contact.form.error', 'en') }}"></div>
                    <div class="full"><div class="muted-divider"></div></div>
                    <div class="full">
                        <div class="landing-card-head" style="padding:0 0 14px;border:0;background:transparent">
                            <div><h3>CAPTCHA Popup</h3><p>The popup opens only after the visitor submits a valid demo form. The request is stored only after the answer is verified on the server.</p></div>
                            <label class="section-toggle"><input type="hidden" name="contact[captcha][enabled]" value="0"><input type="checkbox" name="contact[captcha][enabled]" value="1" @checked($value('contact.captcha.enabled', true))> Enabled</label>
                        </div>
                    </div>
                    <div><label>Popup Title Bangla</label><input name="contact[captcha][title][bn]" value="{{ $trans('contact.captcha.title', 'bn') }}"></div><div><label>Popup Title English</label><input name="contact[captcha][title][en]" value="{{ $trans('contact.captcha.title', 'en') }}"></div>
                    <div><label>Instruction Bangla</label><textarea name="contact[captcha][instruction][bn]">{{ $trans('contact.captcha.instruction', 'bn') }}</textarea></div><div><label>Instruction English</label><textarea name="contact[captcha][instruction][en]">{{ $trans('contact.captcha.instruction', 'en') }}</textarea></div>
                    <div><label>Answer Placeholder Bangla</label><input name="contact[captcha][placeholder][bn]" value="{{ $trans('contact.captcha.placeholder', 'bn') }}"></div><div><label>Answer Placeholder English</label><input name="contact[captcha][placeholder][en]" value="{{ $trans('contact.captcha.placeholder', 'en') }}"></div>
                    <div><label>Verify Button Bangla</label><input name="contact[captcha][verify_button][bn]" value="{{ $trans('contact.captcha.verify_button', 'bn') }}"></div><div><label>Verify Button English</label><input name="contact[captcha][verify_button][en]" value="{{ $trans('contact.captcha.verify_button', 'en') }}"></div>
                    <div><label>Refresh Button Bangla</label><input name="contact[captcha][refresh_button][bn]" value="{{ $trans('contact.captcha.refresh_button', 'bn') }}"></div><div><label>Refresh Button English</label><input name="contact[captcha][refresh_button][en]" value="{{ $trans('contact.captcha.refresh_button', 'en') }}"></div>
                    <div><label>Cancel Button Bangla</label><input name="contact[captcha][cancel_button][bn]" value="{{ $trans('contact.captcha.cancel_button', 'bn') }}"></div><div><label>Cancel Button English</label><input name="contact[captcha][cancel_button][en]" value="{{ $trans('contact.captcha.cancel_button', 'en') }}"></div>
                    <div><label>Loading Message Bangla</label><textarea name="contact[captcha][loading_message][bn]">{{ $trans('contact.captcha.loading_message', 'bn') }}</textarea></div><div><label>Loading Message English</label><textarea name="contact[captcha][loading_message][en]">{{ $trans('contact.captcha.loading_message', 'en') }}</textarea></div>
                    <div><label>Invalid/Expired Message Bangla</label><textarea name="contact[captcha][invalid_message][bn]">{{ $trans('contact.captcha.invalid_message', 'bn') }}</textarea></div><div><label>Invalid/Expired Message English</label><textarea name="contact[captcha][invalid_message][en]">{{ $trans('contact.captcha.invalid_message', 'en') }}</textarea></div>
                </div>
            </section>

            <section class="landing-card landing-section-panel {{ $activeSection === 'footer' ? 'is-active' : '' }}" id="footer" data-section-panel="footer">
                <div class="landing-card-head"><div><h3>Footer</h3><p>Control footer descriptive text.</p></div></div>
                <div class="landing-card-body landing-grid">
                    <div><label>Footer Text Bangla</label><textarea name="footer[text][bn]">{{ $trans('footer.text', 'bn') }}</textarea></div>
                    <div><label>Footer Text English</label><textarea name="footer[text][en]">{{ $trans('footer.text', 'en') }}</textarea></div>
                </div>
            </section>

            <div class="form-actions sticky-actions">
                <button type="submit" class="button btn-primary">Save Landing Page</button>
                <a href="{{ route('landing.show', ['preview' => 1]) }}" target="_blank" class="button btn-outline">Preview</a>
            </div>
        </form>
        </div>
    </div>

    <aside class="right-stack">
        <div class="card info-card">
            <h3>Admin Control</h3>
            <p class="hint">This editor now uses a fully separate Landing Admin sidebar. Admins open one section at a time without editing JSON or entering the accounting dashboard.</p>
            <div class="step-list">
                <div class="step-row"><div class="nav-icon">1</div><div><strong>Edit Cards</strong><small>Change labels, headings, packages, FAQ, buttons and contact form text.</small></div></div>
                <div class="step-row"><div class="nav-icon">2</div><div><strong>Add Details</strong><small>Use Add buttons to add cards, options, package features, FAQ or testimonials.</small></div></div>
                <div class="step-row"><div class="nav-icon">3</div><div><strong>Preview</strong><small>Review unpublished content before publishing.</small></div></div>
            </div>
        </div>

        <div class="card info-card">
            <h3>Reset Default</h3>
            <p class="hint">Restore the uploaded HisebGhor landing-page content and default green theme.</p>
            <form method="POST" action="{{ route('landing-admin.reset') }}" onsubmit="return confirm('Reset landing page to default HisebGhor content?')" data-frontend-form>
                @csrf
                <button type="submit" class="button btn-ghost" style="width:100%;margin-top:12px">Reset to Default</button>
            </form>
        </div>

        <div class="card info-card">
            <h3>Route Summary</h3>
            <div class="step-list">
                <div class="step-row"><div class="nav-icon">/</div><div><strong>Public Landing</strong><small>Always opens landing first, whether user is logged in or logged out.</small></div></div>
                <div class="step-row"><div class="nav-icon">A</div><div><strong>Admin Editor</strong><small>Protected by the separate Landing Admin login.</small></div></div>
                <div class="step-row"><div class="nav-icon">D</div><div><strong>Demo Requests</strong><small>Historical inquiries remain available below.</small></div></div>
            </div>
        </div>
    </aside>
</div>

<div class="card table-card" style="margin-top:22px">
    <div class="panel-head" style="padding:18px 18px 0">
        <div>
            <h3>Latest Demo Inquiries</h3>
            <p class="hint" style="margin-top:4px">The landing contact form stores requests here for admin follow-up.</p>
        </div>
    </div>
    <div class="table-wrap">
        <table>
            <thead><tr><th>Date</th><th>Name</th><th>Business</th><th>Mobile</th><th>Email</th><th>Message</th><th>Status</th><th>Action</th></tr></thead>
            <tbody>
            @forelse($inquiries as $inquiry)
                <tr>
                    <td>{{ $inquiry->created_at?->format('d M Y') }}</td><td class="strong">{{ $inquiry->name }}</td><td>{{ $inquiry->business_name ?: '—' }}</td><td>{{ $inquiry->mobile ?: '—' }}</td><td>{{ $inquiry->email ?: '—' }}</td><td style="min-width:260px;white-space:normal">{{ $inquiry->message ?: '—' }}</td>
                    <td><form method="POST" action="{{ route('landing-admin.inquiries.update', $inquiry) }}" data-frontend-form>@csrf @method('PUT')<select name="status" onchange="this.form.submit()" style="min-width:140px">@foreach($statuses as $status)<option value="{{ $status }}" @selected($inquiry->status === $status)>{{ $status }}</option>@endforeach</select></form></td>
                    <td><form method="POST" action="{{ route('landing-admin.inquiries.destroy', $inquiry) }}" data-delete-form>@csrf @method('DELETE')<button type="submit" class="button btn-ghost" onclick="return confirm('Delete this inquiry?')">Delete</button></form></td>
                </tr>
            @empty
                <tr data-empty="true"><td colspan="8" class="muted">No demo inquiry found yet.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>

<template data-template="nav_links"><div class="repeat-card" data-repeat-item><div class="repeat-card-head"><div class="repeat-card-title">Menu Item</div><button type="button" class="button btn-ghost btn-small danger-link" data-remove-card>Remove</button></div><div class="landing-grid three"><div><label>Bangla Label</label><input name="nav_links[__INDEX__][label][bn]"></div><div><label>English Label</label><input name="nav_links[__INDEX__][label][en]"></div><div><label>Link / Section ID</label><input name="nav_links[__INDEX__][href]" value="#contact"></div></div></div></template>
<template data-template="hero_buttons"><div class="repeat-card" data-repeat-item><div class="repeat-card-head"><div class="repeat-card-title">Hero Button</div><button type="button" class="button btn-ghost btn-small danger-link" data-remove-card>Remove</button></div><div class="landing-grid three"><div><label>Bangla Label</label><input name="hero[buttons][__INDEX__][label][bn]"></div><div><label>English Label</label><input name="hero[buttons][__INDEX__][label][en]"></div><div><label>Style</label><select name="hero[buttons][__INDEX__][style]"><option value="primary">Primary</option><option value="outline">Outline</option><option value="dark">Dark</option></select></div><div class="full"><label>Link</label><input name="hero[buttons][__INDEX__][href]" value="#contact"></div></div></div></template>
<template data-template="trust_items"><div class="repeat-card" data-repeat-item><div class="repeat-card-head"><div class="repeat-card-title">Trust Item</div><button type="button" class="button btn-ghost btn-small danger-link" data-remove-card>Remove</button></div><div class="landing-grid"><div><label>Bangla</label><input name="trust_items[__INDEX__][bn]"></div><div><label>English</label><input name="trust_items[__INDEX__][en]"></div></div></div></template>
<template data-template="why_cards"><div class="repeat-card" data-repeat-item><div class="repeat-card-head"><div class="repeat-card-title">Feature Card</div><button type="button" class="button btn-ghost btn-small danger-link" data-remove-card>Remove</button></div><div class="landing-grid"><div><label>Icon</label><input name="why_cards[__INDEX__][icon]" value="✓"></div><div></div><div><label>Title Bangla</label><input name="why_cards[__INDEX__][title][bn]"></div><div><label>Title English</label><input name="why_cards[__INDEX__][title][en]"></div><div><label>Body Bangla</label><textarea name="why_cards[__INDEX__][body][bn]"></textarea></div><div><label>Body English</label><textarea name="why_cards[__INDEX__][body][en]"></textarea></div></div></div></template>
<template data-template="screens"><div class="repeat-card" data-repeat-item><div class="repeat-card-head"><div class="repeat-card-title">Screen Card</div><button type="button" class="button btn-ghost btn-small danger-link" data-remove-card>Remove</button></div><div class="landing-grid"><div class="full image-admin-field"><label>Feature Image <small>PNG, JPG, WEBP or GIF; any image ratio; max 4 MB</small></label><input type="file" name="screens[__INDEX__][image]" accept="image/*" data-optional="true" data-file-input data-screen-image><input type="hidden" name="screens[__INDEX__][image_path]" value=""><input type="hidden" name="screens[__INDEX__][image_name]" value=""><input type="text" class="image-name-display" value="" placeholder="No image uploaded for this card" readonly data-file-name-display data-current-name="" data-optional="true"><span class="hint">Upload any portrait, square, or landscape image. It will automatically fit inside a fixed 16:9 frame on the landing page without distortion.</span></div><div><label>Title Bangla</label><input name="screens[__INDEX__][title][bn]"></div><div><label>Title English</label><input name="screens[__INDEX__][title][en]"></div><div><label>Body Bangla</label><textarea name="screens[__INDEX__][body][bn]"></textarea></div><div><label>Body English</label><textarea name="screens[__INDEX__][body][en]"></textarea></div></div></div></template>
<template data-template="audiences"><div class="repeat-card" data-repeat-item><div class="repeat-card-head"><div class="repeat-card-title">Business Type Card</div><button type="button" class="button btn-ghost btn-small danger-link" data-remove-card>Remove</button></div><div class="landing-grid"><div><label>Title Bangla</label><input name="audiences[__INDEX__][title][bn]"></div><div><label>Title English</label><input name="audiences[__INDEX__][title][en]"></div><div><label>Body Bangla</label><textarea name="audiences[__INDEX__][body][bn]"></textarea></div><div><label>Body English</label><textarea name="audiences[__INDEX__][body][en]"></textarea></div></div></div></template>
<template data-template="packages">
    <div class="repeat-card package-admin-card" data-repeat-item>
        <div class="repeat-card-head"><div class="repeat-card-title">Implementation Package</div><button type="button" class="button btn-ghost btn-small danger-link" data-remove-card>Remove</button></div>
        <div class="landing-grid three">
            <div><label>Package Icon</label><select name="packages[__INDEX__][icon]"><option value="cloud">Cloud</option><option value="building">Standard / Building</option><option value="server">Server</option></select></div>
            <div><label>Name Bangla</label><input name="packages[__INDEX__][name][bn]"></div>
            <div><label>Name English</label><input name="packages[__INDEX__][name][en]"></div>
            <div><label>Recommended Package?</label><select name="packages[__INDEX__][popular]"><option value="0">No</option><option value="1">Yes</option></select></div>
            <div><label>Top Ribbon Bangla</label><input name="packages[__INDEX__][popular_label][bn]" value="★ Recommended"></div>
            <div><label>Top Ribbon English</label><input name="packages[__INDEX__][popular_label][en]" value="★ Recommended"></div>
            <div><label>Small Tag Bangla</label><input name="packages[__INDEX__][tag][bn]" value="Setup"></div>
            <div><label>Small Tag English</label><input name="packages[__INDEX__][tag][en]" value="Setup"></div>
            <div></div>
            <div><label>Business Description Bangla</label><textarea name="packages[__INDEX__][body][bn]"></textarea></div>
            <div><label>Business Description English</label><textarea name="packages[__INDEX__][body][en]"></textarea></div>
            <div></div>
        </div>
        <div class="package-fee-admin-grid">
            <div class="package-fee-admin-card">
                <strong>Installation Fee</strong>
                <div class="landing-grid">
                    <div><label>Label Bangla</label><input name="packages[__INDEX__][fees][installation][label][bn]" value="Installation Fee"></div>
                    <div><label>Label English</label><input name="packages[__INDEX__][fees][installation][label][en]" value="Installation Fee"></div>
                    <div class="full"><label>Amount / Value</label><input name="packages[__INDEX__][fees][installation][amount]" placeholder="Example: ৳35,000 – ৳50,000"></div>
                    <div><label>Note Bangla</label><input name="packages[__INDEX__][fees][installation][note][bn]" value="One-time"></div>
                    <div><label>Note English</label><input name="packages[__INDEX__][fees][installation][note][en]" value="One-time"></div>
                </div>
            </div>
            <div class="package-fee-admin-card">
                <strong>Maintenance Fee</strong>
                <div class="landing-grid">
                    <div><label>Label Bangla</label><input name="packages[__INDEX__][fees][maintenance][label][bn]" value="Maintenance Fee"></div>
                    <div><label>Label English</label><input name="packages[__INDEX__][fees][maintenance][label][en]" value="Maintenance Fee"></div>
                    <div class="full"><label>Amount / Value</label><input name="packages[__INDEX__][fees][maintenance][amount]" placeholder="Example: ৳5,000"></div>
                    <div><label>Note Bangla</label><input name="packages[__INDEX__][fees][maintenance][note][bn]" value="/month"></div>
                    <div><label>Note English</label><input name="packages[__INDEX__][fees][maintenance][note][en]" value="/month"></div>
                </div>
            </div>
            <div class="package-fee-admin-card">
                <strong>Server Hosting</strong>
                <div class="landing-grid">
                    <div><label>Label Bangla</label><input name="packages[__INDEX__][fees][hosting][label][bn]" value="Server Hosting"></div>
                    <div><label>Label English</label><input name="packages[__INDEX__][fees][hosting][label][en]" value="Server Hosting"></div>
                    <div class="full"><label>Amount / Value</label><input name="packages[__INDEX__][fees][hosting][amount]" value="Actual cost"></div>
                    <div><label>Note Bangla</label><input name="packages[__INDEX__][fees][hosting][note][bn]" value="/month"></div>
                    <div><label>Note English</label><input name="packages[__INDEX__][fees][hosting][note][en]" value="/month"></div>
                </div>
            </div>
        </div>
        <div class="landing-grid" style="margin-top:14px">
            <div><label>Features Bangla <small>One feature per line</small></label><textarea name="packages[__INDEX__][features_bn]" class="tall"></textarea></div>
            <div><label>Features English <small>One feature per line</small></label><textarea name="packages[__INDEX__][features_en]" class="tall"></textarea></div>
        </div>
    </div>
</template>
<template data-template="pricing_notes">
    <div class="repeat-card" data-repeat-item>
        <div class="repeat-card-head"><div class="repeat-card-title">Important Note</div><button type="button" class="button btn-ghost btn-small danger-link" data-remove-card>Remove</button></div>
        <div class="landing-grid three">
            <div><label>Icon</label><select name="pricing_notes[__INDEX__][icon]"><option value="tag">Pricing Tag</option><option value="server">Server</option><option value="wrench">Maintenance</option></select></div>
            <div><label>Title Bangla</label><input name="pricing_notes[__INDEX__][title][bn]"></div>
            <div><label>Title English</label><input name="pricing_notes[__INDEX__][title][en]"></div>
            <div><label>Body Bangla</label><textarea name="pricing_notes[__INDEX__][body][bn]"></textarea></div>
            <div><label>Body English</label><textarea name="pricing_notes[__INDEX__][body][en]"></textarea></div>
        </div>
    </div>
</template>
<template data-template="testimonials"><div class="repeat-card" data-repeat-item><div class="repeat-card-head"><div class="repeat-card-title">Testimonial</div><button type="button" class="button btn-ghost btn-small danger-link" data-remove-card>Remove</button></div><div class="landing-grid"><div><label>Name</label><input name="testimonials[__INDEX__][name]"></div><div><label>Avatar Text</label><input name="testimonials[__INDEX__][avatar]"></div><div><label>Role Bangla</label><input name="testimonials[__INDEX__][role][bn]"></div><div><label>Role English</label><input name="testimonials[__INDEX__][role][en]"></div><div><label>Quote Bangla</label><textarea name="testimonials[__INDEX__][quote][bn]"></textarea></div><div><label>Quote English</label><textarea name="testimonials[__INDEX__][quote][en]"></textarea></div></div></div></template>
<template data-template="faqs"><div class="repeat-card" data-repeat-item><div class="repeat-card-head"><div class="repeat-card-title">FAQ</div><button type="button" class="button btn-ghost btn-small danger-link" data-remove-card>Remove</button></div><div class="landing-grid"><div><label>Question Bangla</label><input name="faqs[__INDEX__][question][bn]"></div><div><label>Question English</label><input name="faqs[__INDEX__][question][en]"></div><div><label>Answer Bangla</label><textarea name="faqs[__INDEX__][answer][bn]"></textarea></div><div><label>Answer English</label><textarea name="faqs[__INDEX__][answer][en]"></textarea></div><div class="full"><label class="section-toggle"><input type="hidden" name="faqs[__INDEX__][open]" value="0"><input type="checkbox" name="faqs[__INDEX__][open]" value="1"> Open by default</label></div></div></div></template>
@endsection

@push('scripts')
<script>
(function () {
    let counter = Date.now();

    const sectionLinks = Array.from(document.querySelectorAll('[data-section-target]'));
    const sectionPanels = Array.from(document.querySelectorAll('[data-section-panel]'));

    function activateSection(sectionId, updateUrl = true) {
        if (!sectionId || !document.querySelector('[data-section-panel="' + sectionId + '"]')) {
            sectionId = 'basic';
        }

        sectionLinks.forEach(function (link) {
            link.classList.toggle('is-active', link.getAttribute('data-section-target') === sectionId);
        });

        sectionPanels.forEach(function (panel) {
            panel.classList.toggle('is-active', panel.getAttribute('data-section-panel') === sectionId);
        });

        const activeInput = document.getElementById('landingActiveSection');
        if (activeInput) {
            activeInput.value = sectionId;
        }

        const form = document.querySelector('.landing-form');
        if (form) {
            const actionUrl = new URL(form.getAttribute('action'), window.location.origin);
            actionUrl.searchParams.set('section', sectionId);
            form.setAttribute('action', actionUrl.pathname + actionUrl.search);
        }

        if (updateUrl) {
            const url = new URL(window.location.href);
            url.searchParams.set('section', sectionId);
            history.replaceState(null, '', url.pathname + url.search + url.hash);
        }
    }

    function sectionForRepeater(key) {
        const map = {
            nav_links: 'nav',
            hero_buttons: 'hero',
            trust_items: 'hero',
            why_cards: 'why',
            screens: 'features',
            audiences: 'audience',
            packages: 'pricing',
            pricing_notes: 'pricing',
            testimonials: 'testimonials',
            faqs: 'faq'
        };

        return map[key] || 'basic';
    }

    function revealNewCard(card) {
        if (!card) {
            return;
        }

        card.classList.add('new-repeat-card-highlight');
        card.scrollIntoView({ behavior: 'smooth', block: 'center' });

        const firstControl = card.querySelector('input:not([type="hidden"]), textarea, select');
        if (firstControl) {
            setTimeout(function () { firstControl.focus(); }, 350);
        }

        setTimeout(function () {
            card.classList.remove('new-repeat-card-highlight');
        }, 3500);
    }

    document.addEventListener('click', function (event) {
        const sectionButton = event.target.closest('[data-section-target]');
        const addButton = event.target.closest('[data-add]');
        const removeButton = event.target.closest('[data-remove-card]');

        if (sectionButton) {
            activateSection(sectionButton.getAttribute('data-section-target'));
            const activePanel = document.querySelector('[data-section-panel].is-active');
            if (activePanel) {
                activePanel.scrollIntoView({ behavior: 'smooth', block: 'start' });
            }
        }

        if (addButton) {
            const key = addButton.getAttribute('data-add');
            const sectionId = sectionForRepeater(key);
            activateSection(sectionId);

            const target = document.querySelector('[data-repeater="' + key + '"]');
            const template = document.querySelector('template[data-template="' + key + '"]');

            if (!target || !template) {
                return;
            }

            counter += 1;
            target.insertAdjacentHTML('beforeend', template.innerHTML.replaceAll('__INDEX__', String(counter)));
            revealNewCard(target.querySelector('[data-repeat-item]:last-child'));
        }

        if (removeButton) {
            const card = removeButton.closest('[data-repeat-item]');
            if (card) {
                card.remove();
            }
        }
    });

    function humanizeName(name) {
        return String(name || '')
            .replace(/\\?[\[\]]+/g, ' ')
            .replace(/_/g, ' ')
            .replace(/\s+/g, ' ')
            .replace(/\b(bn|en)\b/g, function (match) { return match === 'bn' ? 'Bangla' : 'English'; })
            .replace(/\b\w/g, function (letter) { return letter.toUpperCase(); })
            .trim();
    }

    function controlLabel(control) {
        const wrapper = control.closest('div');
        const label = wrapper ? wrapper.querySelector('label') : null;
        return label ? label.textContent.replace('*', '').trim() : humanizeName(control.name);
    }

    function validationControls(form) {
        return Array.from(form.querySelectorAll('input, textarea, select')).filter(function (control) {
            if (!control.name || control.disabled) {
                return false;
            }

            if (control.readOnly || control.dataset.optional === 'true') {
                return false;
            }

            const type = (control.getAttribute('type') || '').toLowerCase();
            return !['hidden', 'checkbox', 'radio', 'submit', 'button', 'file'].includes(type);
        });
    }

    function clearValidationState(form) {
        form.querySelectorAll('.landing-validation-invalid').forEach(function (control) {
            control.classList.remove('landing-validation-invalid');
        });
        form.querySelectorAll('.landing-validation-error').forEach(function (message) {
            message.remove();
        });
        form.querySelectorAll('.landing-validation-invalid-list').forEach(function (list) {
            list.classList.remove('landing-validation-invalid-list');
        });
    }

    function cssEscapeValue(value) {
        if (window.CSS && typeof window.CSS.escape === 'function') {
            return window.CSS.escape(value);
        }

        return String(value).replace(/[^a-zA-Z0-9_-]/g, function (character) {
            return '\\' + character;
        });
    }

    function showControlError(control, message) {
        control.classList.add('landing-validation-invalid');

        const existing = control.parentElement ? control.parentElement.querySelector('.landing-validation-error[data-for="' + cssEscapeValue(control.name) + '"]') : null;
        if (existing) {
            existing.textContent = message;
            return;
        }

        const error = document.createElement('span');
        error.className = 'landing-validation-error';
        error.dataset.for = control.name;
        error.textContent = message;
        control.insertAdjacentElement('afterend', error);
    }

    function clearControlError(control) {
        control.classList.remove('landing-validation-invalid');
        const error = control.parentElement
            ? control.parentElement.querySelector('.landing-validation-error[data-for="' + cssEscapeValue(control.name) + '"]')
            : null;

        if (error) {
            error.remove();
        }
    }

    function updateScreenImagePreview(input, imageUrl) {
        const wrapper = input.closest('.image-admin-field');
        if (!wrapper) {
            return;
        }

        let preview = wrapper.querySelector('.screen-image-admin-preview');
        if (!preview) {
            preview = document.createElement('img');
            preview.className = 'image-admin-preview screen-image-admin-preview';
            preview.alt = 'Selected feature screen image';
            wrapper.appendChild(preview);
        }

        preview.src = imageUrl;
    }

    function validateScreenImageInput(input) {
        if (!input.matches('[data-screen-image]')) {
            return;
        }

        const file = input.files && input.files[0] ? input.files[0] : null;
        clearControlError(input);

        if (!file) {
            input.dataset.imageValid = 'true';
            return;
        }

        input.dataset.imageValid = 'pending';

        if (input.dataset.previewUrl) {
            URL.revokeObjectURL(input.dataset.previewUrl);
        }

        const objectUrl = URL.createObjectURL(file);
        input.dataset.previewUrl = objectUrl;

        const image = new Image();
        image.onload = function () {
            input.dataset.imageValid = 'true';
            input.dataset.imageDimensions = image.naturalWidth + '×' + image.naturalHeight;
            updateScreenImagePreview(input, objectUrl);
            clearControlError(input);
        };
        image.onerror = function () {
            input.dataset.imageValid = 'false';
            showControlError(input, 'The selected feature screen image could not be read. Please choose another PNG, JPG, WEBP or GIF image.');
        };
        image.src = objectUrl;
    }

    function showSummary(errors) {
        const summary = document.getElementById('landingClientValidationSummary');
        if (!summary) {
            return;
        }

        if (!errors.length) {
            summary.classList.remove('show');
            summary.innerHTML = '';
            return;
        }

        summary.innerHTML = '<strong>Please fill all required landing-page admin fields before saving.</strong><ul>' +
            errors.slice(0, 12).map(function (error) { return '<li>' + error.message + '</li>'; }).join('') +
            (errors.length > 12 ? '<li>And ' + (errors.length - 12) + ' more required field(s).</li>' : '') +
            '</ul>';
        summary.classList.add('show');
    }

    function validateLandingForm(form) {
        clearValidationState(form);

        const errors = [];
        const requiredRepeaters = [
            'nav_links', 'hero_buttons', 'trust_items', 'why_cards',
            'screens', 'audiences', 'packages', 'pricing_notes', 'testimonials', 'faqs'
        ];

        requiredRepeaters.forEach(function (key) {
            const list = form.querySelector('[data-repeater="' + key + '"]');
            if (list && !list.querySelector('[data-repeat-item]')) {
                list.classList.add('landing-validation-invalid-list');
                errors.push({
                    control: list,
                    section: sectionForRepeater(key),
                    message: humanizeName(key) + ' must have at least one card/item.'
                });
            }
        });

        validationControls(form).forEach(function (control) {
            const value = String(control.value || '').trim();
            const label = controlLabel(control);

            if (value === '') {
                const message = label + ' is required.';
                showControlError(control, message);
                errors.push({
                    control: control,
                    section: (control.closest('[data-section-panel]') ? control.closest('[data-section-panel]').getAttribute('data-section-panel') : 'basic'),
                    message: message
                });
                return;
            }

            if ((control.getAttribute('type') || '').toLowerCase() === 'email') {
                const validEmail = /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(value);
                if (!validEmail) {
                    const message = label + ' must be a valid email address.';
                    showControlError(control, message);
                    errors.push({
                        control: control,
                        section: (control.closest('[data-section-panel]') ? control.closest('[data-section-panel]').getAttribute('data-section-panel') : 'basic'),
                        message: message
                    });
                }
            }
        });

        form.querySelectorAll('[data-screen-image]').forEach(function (input) {
            const hasSelectedFile = input.files && input.files.length > 0;
            if (!hasSelectedFile || input.dataset.imageValid === 'true') {
                return;
            }

            const message = input.dataset.imageValid === 'pending'
                ? 'Please wait while the selected feature screen image is checked.'
                : 'The selected feature screen image could not be read. Please choose another PNG, JPG, WEBP or GIF image.';

            showControlError(input, message);
            errors.push({
                control: input,
                section: 'features',
                message: message
            });
        });

        showSummary(errors);

        if (errors.length > 0) {
            const first = errors[0];
            activateSection(first.section || 'basic');
            setTimeout(function () {
                if (first.control && typeof first.control.focus === 'function') {
                    first.control.focus({ preventScroll: true });
                }
                if (first.control && typeof first.control.scrollIntoView === 'function') {
                    first.control.scrollIntoView({ behavior: 'smooth', block: 'center' });
                }
            }, 120);
            return false;
        }

        return true;
    }

    const form = document.querySelector('.landing-form');
    if (form) {
        validationControls(form).forEach(function (control) {
            const wrapper = control.closest('div');
            const label = wrapper ? wrapper.querySelector('label') : null;
            if (label && !label.querySelector('.required-auto')) {
                label.insertAdjacentHTML('beforeend', ' <span class="required-auto">*</span>');
            }
        });

        form.addEventListener('input', function (event) {
            const control = event.target.closest('input, textarea, select');
            if (!control) {
                return;
            }

            control.classList.remove('landing-validation-invalid');
            const error = control.parentElement ? control.parentElement.querySelector('.landing-validation-error[data-for="' + cssEscapeValue(control.name) + '"]') : null;
            if (error) {
                error.remove();
            }
        });

        form.addEventListener('change', function (event) {
            const input = event.target.closest('[data-file-input]');
            if (!input) {
                return;
            }

            const wrapper = input.closest('.image-admin-field');
            const display = wrapper ? wrapper.querySelector('[data-file-name-display]') : null;
            if (display) {
                display.value = input.files && input.files.length ? input.files[0].name : (display.dataset.currentName || '');
            }

            validateScreenImageInput(input);
        });

        form.addEventListener('submit', function (event) {
            if (!validateLandingForm(form)) {
                event.preventDefault();
                event.stopPropagation();
            }
        });
    }

    const params = new URLSearchParams(window.location.search);
    const initialSection = params.get('section') || (window.location.hash ? window.location.hash.substring(1) : '{{ $activeSection }}');
    activateSection(initialSection, false);
})();
</script>
@endpush
