<?php $__env->startSection('title', 'Landing Page Editor | HisebGhor'); ?>

<?php $__env->startPush('styles'); ?>
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
    .image-admin-field{border:1px dashed #cbd5e1;border-radius:18px;background:#f8fafc;padding:14px}.image-admin-preview{margin-top:12px;max-width:100%;max-height:210px;border-radius:16px;border:1px solid #e5e7eb;background:#fff;object-fit:cover;display:block}.image-name-display{margin-top:8px;background:#f9fafb!important;color:#475467!important}.image-admin-field input[type=file]{padding:10px;background:#fff}.image-admin-field .hint{display:block;margin-top:7px}.brand-logo-admin-preview{max-height:120px;object-fit:contain;background:#fff;padding:12px}.brand-logo-upload-only{background:#f8fafc}
    @media(max-width:1250px){.landing-admin-grid{grid-template-columns:1fr}.right-stack{position:static}.landing-editor-layout{grid-template-columns:1fr}}
    @media(max-width:900px){.landing-editor-layout{grid-template-columns:1fr}.landing-section-menu{position:static}.landing-section-list{grid-template-columns:repeat(2,minmax(0,1fr))}.landing-grid,.landing-grid.three{grid-template-columns:1fr}}
    @media(max-width:640px){.landing-section-list{grid-template-columns:1fr}}
</style>
<?php $__env->stopPush(); ?>

<?php $__env->startSection('content'); ?>
<?php
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
?>

<div class="page-title">
    <div>
        <span class="page-label">Landing Page</span>
        <h2>HisebGhor Landing Page Control</h2>
        <p>Admin-controlled cards for every public landing-page section. Use the dedicated Landing Admin dashboard menu to open each section, then save without touching accounting logic.</p>
        <?php if($updatedAt): ?>
            <p class="hint" style="margin-top:6px">Last updated <?php echo e($updatedAt->format('d M Y h:i A')); ?><?php if($updatedBy): ?> by <?php echo e($updatedBy->name); ?><?php endif; ?>.</p>
        <?php endif; ?>
    </div>
    <div class="actions" style="border-top:0;padding-top:0">
        <a href="<?php echo e(route('landing.show', ['preview' => 1])); ?>" target="_blank" class="button btn-ghost">Preview</a>
        <a href="<?php echo e(route('landing.public')); ?>" target="_blank" class="button btn-outline">Open Public Page</a>
    </div>
</div>

<?php if(session('status')): ?>
    <div class="card" style="padding:14px 18px;margin-bottom:18px;border-color:#bbf7d0;background:#f0fdf4;color:#067647;font-weight:850">
        <?php echo e(session('status')); ?>

    </div>
<?php endif; ?>

<?php if($errors->any()): ?>
    <div class="card" style="padding:14px 18px;margin-bottom:18px;border-color:#fecaca;background:#fef2f2;color:#b42318;font-weight:750">
        <strong>Please fix the following:</strong>
        <ul style="margin:8px 0 0;padding-left:20px">
            <?php $__currentLoopData = $errors->all(); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $error): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                <li><?php echo e($error); ?></li>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
        </ul>
    </div>
<?php endif; ?>

<div id="landingClientValidationSummary" class="landing-validation-summary" role="alert" aria-live="polite"></div>

<div class="landing-admin-grid">
    <div class="landing-editor-layout">
        <div class="landing-editor-main">
        <form method="POST" action="<?php echo e(route('landing-admin.update', ['section' => $activeSection])); ?>" class="landing-form" data-frontend-form enctype="multipart/form-data" novalidate>
            <?php echo csrf_field(); ?>
            <?php echo method_field('PUT'); ?>
            <input type="hidden" name="active_section" id="landingActiveSection" value="<?php echo e($activeSection); ?>">

            <section class="landing-card landing-section-panel <?php echo e($activeSection === 'basic' ? 'is-active' : ''); ?>" id="basic" data-section-panel="basic">
                <div class="landing-card-head">
                    <div><h3>Basic, Brand & Theme</h3><p>Control publish status, SEO title, brand logo image, language and public theme colors.</p></div>
                </div>
                <div class="landing-card-body landing-grid">
                    <div>
                        <label>Published Status</label>
                        <select name="is_published">
                            <option value="1" <?php if(old('is_published', $isPublished ? '1' : '0') === '1'): echo 'selected'; endif; ?>>Published</option>
                            <option value="0" <?php if(old('is_published', $isPublished ? '1' : '0') === '0'): echo 'selected'; endif; ?>>Unpublished / Preview only</option>
                        </select>
                    </div>
                    <div>
                        <label>Default Language</label>
                        <select name="meta[default_lang]">
                            <option value="bn" <?php if($value('meta.default_lang', 'bn') === 'bn'): echo 'selected'; endif; ?>>Bangla first</option>
                            <option value="en" <?php if($value('meta.default_lang', 'bn') === 'en'): echo 'selected'; endif; ?>>English first</option>
                        </select>
                    </div>
                    <div class="full"><label>Browser Title <span class="required">*</span></label><input name="meta[title]" value="<?php echo e($value('meta.title')); ?>" required></div>
                    <div class="full"><label>Meta Description</label><textarea name="meta[description]"><?php echo e($value('meta.description')); ?></textarea></div>
                    <div class="full image-admin-field brand-logo-upload-only">
                        <label>
                            Brand Logo Image
                            <small>Upload the complete logo artwork. This one image is used in both header and footer. PNG, JPG, WEBP or GIF; max 4 MB</small>
                        </label>
                        <input type="file" name="brand[logo][image]" accept="image/*" data-optional="true" data-file-input>
                        <input type="hidden" name="brand[logo][image_path]" value="<?php echo e($brandLogoPath); ?>">
                        <input type="hidden" name="brand[logo][image_name]" value="<?php echo e($brandLogoName); ?>">
                        <input type="text" class="image-name-display" value="<?php echo e($brandLogoName); ?>" placeholder="No brand logo image uploaded yet" readonly data-file-name-display data-current-name="<?php echo e($brandLogoName); ?>" data-optional="true">
                        <span class="hint">Upload the whole brand lockup as one image, for example icon + wordmark + small slogan. This single image will render in the public header and footer.</span>
                        <?php if($brandLogoPath !== ''): ?>
                            <img src="<?php echo e($imageUrl($brandLogoPath)); ?>" alt="<?php echo e($brandLogoName ?: 'Landing brand logo'); ?>" class="image-admin-preview brand-logo-admin-preview">
                        <?php endif; ?>
                    </div>
                    <div><label>Primary Green</label><input name="theme[green]" value="<?php echo e($value('theme.green', '#00a86b')); ?>"></div>
                    <div><label>Dark Green</label><input name="theme[green_dark]" value="<?php echo e($value('theme.green_dark', '#087a52')); ?>"></div>
                    <div><label>Soft Green</label><input name="theme[green_soft]" value="<?php echo e($value('theme.green_soft', '#e9fff5')); ?>"></div>
                    <div><label>Blue Accent</label><input name="theme[blue]" value="<?php echo e($value('theme.blue', '#2563eb')); ?>"></div>
                    <div><label>Gold Accent</label><input name="theme[gold]" value="<?php echo e($value('theme.gold', '#f59e0b')); ?>"></div>
                    <div><label>Background</label><input name="theme[bg]" value="<?php echo e($value('theme.bg', '#f8fafc')); ?>"></div>
                    <div><label>Text Color</label><input name="theme[ink]" value="<?php echo e($value('theme.ink', '#101828')); ?>"></div>
                    <div><label>Muted Text</label><input name="theme[muted]" value="<?php echo e($value('theme.muted', '#667085')); ?>"></div>
                </div>
            </section>

            <section class="landing-card landing-section-panel <?php echo e($activeSection === 'nav' ? 'is-active' : ''); ?>" id="nav" data-section-panel="nav">
                <div class="landing-card-head">
                    <div><h3>Landing Navigation</h3><p>Add or edit public landing-page menu items. Login/admin buttons are intentionally hidden from the public landing page; Landing Admin uses the direct URL.</p></div>
                    <button type="button" class="button btn-outline btn-small" data-add="nav_links">Add Menu</button>
                </div>
                <div class="landing-card-body">
                    <div class="landing-grid">
                        <div><label>Demo Button Text Bangla <span class="required">*</span></label><input name="cta[primary][label][bn]" value="<?php echo e($trans('cta.primary.label', 'bn')); ?>" required></div>
                        <div><label>Demo Button Text English <span class="required">*</span></label><input name="cta[primary][label][en]" value="<?php echo e($trans('cta.primary.label', 'en')); ?>" required></div>
                        <div class="full"><label>Demo Button Link <span class="required">*</span></label><input name="cta[primary][href]" value="<?php echo e($value('cta.primary.href', '#contact')); ?>" placeholder="#contact, https://wa.me/880..., or any URL" required></div>
                        <div class="full"><div class="code-help">This controls the top-right public CTA button text and destination. Use #contact to scroll to the demo form, or paste a WhatsApp/email/website link.</div></div>
                    </div>
                    <div class="muted-divider"></div>
                    <div class="repeat-list" data-repeater="nav_links">
                        <?php $__currentLoopData = array_values($navLinks); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $index => $link): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                            <div class="repeat-card" data-repeat-item>
                                <div class="repeat-card-head"><div class="repeat-card-title">Menu Item</div><button type="button" class="button btn-ghost btn-small danger-link" data-remove-card>Remove</button></div>
                                <div class="landing-grid three">
                                    <div><label>Bangla Label</label><input name="nav_links[<?php echo e($index); ?>][label][bn]" value="<?php echo e(data_get($link, 'label.bn')); ?>"></div>
                                    <div><label>English Label</label><input name="nav_links[<?php echo e($index); ?>][label][en]" value="<?php echo e(data_get($link, 'label.en')); ?>"></div>
                                    <div><label>Link / Section ID</label><input name="nav_links[<?php echo e($index); ?>][href]" value="<?php echo e(data_get($link, 'href', '#')); ?>"></div>
                                </div>
                            </div>
                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                    </div>                </div>
            </section>

            <section class="landing-card landing-section-panel <?php echo e($activeSection === 'hero' ? 'is-active' : ''); ?>" id="hero" data-section-panel="hero">
                <div class="landing-card-head">
                    <div><h3>Hero Section</h3><p>Top headline, buttons, trust labels and live dashboard preview.</p></div>
                    <label class="section-toggle"><input type="hidden" name="hero[enabled]" value="0"><input type="checkbox" name="hero[enabled]" value="1" <?php if($value('hero.enabled', true)): echo 'checked'; endif; ?>> Enabled</label>
                </div>
                <div class="landing-card-body">
                    <div class="landing-grid">
                        <div><label>Eyebrow Bangla</label><input name="hero[eyebrow][bn]" value="<?php echo e($trans('hero.eyebrow', 'bn')); ?>"></div>
                        <div><label>Eyebrow English</label><input name="hero[eyebrow][en]" value="<?php echo e($trans('hero.eyebrow', 'en')); ?>"></div>
                        <div class="full"><label>Hero Title Bangla <span class="required">*</span></label><input name="hero[title][bn]" value="<?php echo e($trans('hero.title', 'bn')); ?>" required></div>
                        <div class="full"><label>Hero Title English <span class="required">*</span></label><input name="hero[title][en]" value="<?php echo e($trans('hero.title', 'en')); ?>" required></div>
                        <div><label>Hero Subtitle Bangla</label><textarea name="hero[subtitle][bn]" class="tall"><?php echo e($trans('hero.subtitle', 'bn')); ?></textarea></div>
                        <div><label>Hero Subtitle English</label><textarea name="hero[subtitle][en]" class="tall"><?php echo e($trans('hero.subtitle', 'en')); ?></textarea></div>
                    </div>
                    <div class="muted-divider"></div>
                    <div class="landing-card-head" style="padding:0 0 14px;border:0;background:transparent"><div><h3>Hero Buttons</h3><p>Admin can add more buttons or links.</p></div><button type="button" class="button btn-outline btn-small" data-add="hero_buttons">Add Button</button></div>
                    <div class="repeat-list" data-repeater="hero_buttons">
                        <?php $__currentLoopData = array_values($heroButtons); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $index => $button): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                            <div class="repeat-card" data-repeat-item>
                                <div class="repeat-card-head"><div class="repeat-card-title">Hero Button</div><button type="button" class="button btn-ghost btn-small danger-link" data-remove-card>Remove</button></div>
                                <div class="landing-grid three">
                                    <div><label>Bangla Label</label><input name="hero[buttons][<?php echo e($index); ?>][label][bn]" value="<?php echo e(data_get($button, 'label.bn')); ?>"></div>
                                    <div><label>English Label</label><input name="hero[buttons][<?php echo e($index); ?>][label][en]" value="<?php echo e(data_get($button, 'label.en')); ?>"></div>
                                    <div><label>Style</label><select name="hero[buttons][<?php echo e($index); ?>][style]"><option value="primary" <?php if(data_get($button, 'style') === 'primary'): echo 'selected'; endif; ?>>Primary</option><option value="outline" <?php if(data_get($button, 'style') === 'outline'): echo 'selected'; endif; ?>>Outline</option><option value="dark" <?php if(data_get($button, 'style') === 'dark'): echo 'selected'; endif; ?>>Dark</option></select></div>
                                    <div class="full"><label>Link</label><input name="hero[buttons][<?php echo e($index); ?>][href]" value="<?php echo e(data_get($button, 'href', '#contact')); ?>"></div>
                                </div>
                            </div>
                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                    </div>
                    <div class="muted-divider"></div>
                    <div class="landing-card-head" style="padding:0 0 14px;border:0;background:transparent"><div><h3>Trust Items</h3><p>Small check-mark labels below the hero button group.</p></div><button type="button" class="button btn-outline btn-small" data-add="trust_items">Add Trust Item</button></div>
                    <div class="repeat-list" data-repeater="trust_items">
                        <?php $__currentLoopData = array_values($trustItems); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $index => $trust): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                            <div class="repeat-card" data-repeat-item>
                                <div class="repeat-card-head"><div class="repeat-card-title">Trust Item</div><button type="button" class="button btn-ghost btn-small danger-link" data-remove-card>Remove</button></div>
                                <div class="landing-grid"><div><label>Bangla</label><input name="trust_items[<?php echo e($index); ?>][bn]" value="<?php echo e(data_get($trust, 'bn')); ?>"></div><div><label>English</label><input name="trust_items[<?php echo e($index); ?>][en]" value="<?php echo e(data_get($trust, 'en')); ?>"></div></div>
                            </div>
                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                    </div>
                    <div class="muted-divider"></div>
                    <h3 class="section-title">Dashboard Preview</h3>
                    <?php
                        $heroDashboard = data_get($landing, 'hero.dashboard', []);
                        $heroImagePath = old('hero.dashboard.image_path', $imagePath($heroDashboard));
                        $heroImageName = old('hero.dashboard.image_name', $imageName($heroDashboard));
                    ?>
                    <div class="landing-grid">
                        <div class="full image-admin-field">
                            <label>Dashboard Preview Image <small>PNG, JPG, WEBP or GIF; max 4 MB</small></label>
                            <input type="file" name="hero[dashboard][image]" accept="image/*" data-optional="true" data-file-input>
                            <input type="hidden" name="hero[dashboard][image_path]" value="<?php echo e($heroImagePath); ?>">
                            <input type="hidden" name="hero[dashboard][image_name]" value="<?php echo e($heroImageName); ?>">
                            <input type="text" class="image-name-display" value="<?php echo e($heroImageName); ?>" placeholder="No dashboard preview image uploaded yet" readonly data-file-name-display data-current-name="<?php echo e($heroImageName); ?>" data-optional="true">
                            <span class="hint">Upload the full dashboard preview as one image. No separate dashboard title, subtitle, status chip, or mock data rows will be shown.</span>
                            <?php if($heroImagePath !== ''): ?>
                                <img src="<?php echo e($imageUrl($heroImagePath)); ?>" alt="<?php echo e($heroImageName ?: 'Dashboard preview image'); ?>" class="image-admin-preview">
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </section>

            <section class="landing-card landing-section-panel <?php echo e($activeSection === 'why' ? 'is-active' : ''); ?>" id="why" data-section-panel="why">
                <div class="landing-card-head"><div><h3>Why HisebGhor Section</h3><p>Section title and the feature cards below it.</p></div><label class="section-toggle"><input type="hidden" name="why[enabled]" value="0"><input type="checkbox" name="why[enabled]" value="1" <?php if($value('why.enabled', true)): echo 'checked'; endif; ?>> Enabled</label></div>
                <div class="landing-card-body">
                    <div class="landing-grid">
                        <div><label>Mini Bangla</label><input name="why[mini][bn]" value="<?php echo e($trans('why.mini', 'bn')); ?>"></div><div><label>Mini English</label><input name="why[mini][en]" value="<?php echo e($trans('why.mini', 'en')); ?>"></div>
                        <div><label>Title Bangla</label><textarea name="why[title][bn]"><?php echo e($trans('why.title', 'bn')); ?></textarea></div><div><label>Title English</label><textarea name="why[title][en]"><?php echo e($trans('why.title', 'en')); ?></textarea></div>
                        <div><label>Subtitle Bangla</label><textarea name="why[subtitle][bn]"><?php echo e($trans('why.subtitle', 'bn')); ?></textarea></div><div><label>Subtitle English</label><textarea name="why[subtitle][en]"><?php echo e($trans('why.subtitle', 'en')); ?></textarea></div>
                    </div>
                    <div class="muted-divider"></div>
                    <div class="landing-card-head" style="padding:0 0 14px;border:0;background:transparent"><div><h3>Why Cards</h3></div><button type="button" class="button btn-outline btn-small" data-add="why_cards">Add Card</button></div>
                    <div class="repeat-list" data-repeater="why_cards">
                        <?php $__currentLoopData = array_values($whyCards); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $index => $card): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                            <div class="repeat-card" data-repeat-item><div class="repeat-card-head"><div class="repeat-card-title">Feature Card</div><button type="button" class="button btn-ghost btn-small danger-link" data-remove-card>Remove</button></div><div class="landing-grid"><div><label>Icon</label><input name="why_cards[<?php echo e($index); ?>][icon]" value="<?php echo e(data_get($card, 'icon', '✓')); ?>"></div><div></div><div><label>Title Bangla</label><input name="why_cards[<?php echo e($index); ?>][title][bn]" value="<?php echo e(data_get($card, 'title.bn')); ?>"></div><div><label>Title English</label><input name="why_cards[<?php echo e($index); ?>][title][en]" value="<?php echo e(data_get($card, 'title.en')); ?>"></div><div><label>Body Bangla</label><textarea name="why_cards[<?php echo e($index); ?>][body][bn]"><?php echo e(data_get($card, 'body.bn')); ?></textarea></div><div><label>Body English</label><textarea name="why_cards[<?php echo e($index); ?>][body][en]"><?php echo e(data_get($card, 'body.en')); ?></textarea></div></div></div>
                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                    </div>
                </div>
            </section>

            <section class="landing-card landing-section-panel <?php echo e($activeSection === 'features' ? 'is-active' : ''); ?>" id="features" data-section-panel="features">
                <div class="landing-card-head"><div><h3>Screenshots & Feature Screens</h3><p>Upload only the white browser/screen preview image. The dark card background, title, and description are controlled separately below.</p></div><label class="section-toggle"><input type="hidden" name="features[enabled]" value="0"><input type="checkbox" name="features[enabled]" value="1" <?php if($value('features.enabled', true)): echo 'checked'; endif; ?>> Enabled</label></div>
                <div class="landing-card-body">
                    <div class="landing-grid"><div><label>Mini Bangla</label><input name="features[mini][bn]" value="<?php echo e($trans('features.mini', 'bn')); ?>"></div><div><label>Mini English</label><input name="features[mini][en]" value="<?php echo e($trans('features.mini', 'en')); ?>"></div><div><label>Title Bangla</label><textarea name="features[title][bn]"><?php echo e($trans('features.title', 'bn')); ?></textarea></div><div><label>Title English</label><textarea name="features[title][en]"><?php echo e($trans('features.title', 'en')); ?></textarea></div><div><label>Subtitle Bangla</label><textarea name="features[subtitle][bn]"><?php echo e($trans('features.subtitle', 'bn')); ?></textarea></div><div><label>Subtitle English</label><textarea name="features[subtitle][en]"><?php echo e($trans('features.subtitle', 'en')); ?></textarea></div></div>
                    <div class="muted-divider"></div>
                    <div class="landing-card-head" style="padding:0 0 14px;border:0;background:transparent"><div><h3>Screen Cards</h3></div><button type="button" class="button btn-outline btn-small" data-add="screens">Add Screen</button></div>
                    <div class="repeat-list" data-repeater="screens">
                        <?php $__currentLoopData = array_values($screens); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $index => $screen): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                            <?php
                                $screenImagePath = old('screens.'.$index.'.image_path', $imagePath($screen));
                                $screenImageName = old('screens.'.$index.'.image_name', $imageName($screen));
                            ?>
                            <div class="repeat-card" data-repeat-item>
                                <div class="repeat-card-head"><div class="repeat-card-title">Screen Card</div><button type="button" class="button btn-ghost btn-small danger-link" data-remove-card>Remove</button></div>
                                <div class="landing-grid">
                                    <div class="full image-admin-field">
                                        <label>White Preview Image <small>PNG, JPG, WEBP or GIF; max 4 MB</small></label>
                                        <input type="file" name="screens[<?php echo e($index); ?>][image]" accept="image/*" data-optional="true" data-file-input>
                                        <input type="hidden" name="screens[<?php echo e($index); ?>][image_path]" value="<?php echo e($screenImagePath); ?>">
                                        <input type="hidden" name="screens[<?php echo e($index); ?>][image_name]" value="<?php echo e($screenImageName); ?>">
                                        <input type="text" class="image-name-display" value="<?php echo e($screenImageName); ?>" placeholder="No white preview image uploaded for this card" readonly data-file-name-display data-current-name="<?php echo e($screenImageName); ?>" data-optional="true">
                                        <span class="hint">This image becomes the white top preview area only. Do not upload the full dark feature card with title/description.</span>
                                        <?php if($screenImagePath !== ''): ?>
                                            <img src="<?php echo e($imageUrl($screenImagePath)); ?>" alt="<?php echo e($screenImageName ?: 'Feature screen image'); ?>" class="image-admin-preview">
                                        <?php endif; ?>
                                    </div>
                                    <div><label>Title Bangla</label><input name="screens[<?php echo e($index); ?>][title][bn]" value="<?php echo e(data_get($screen, 'title.bn')); ?>"></div>
                                    <div><label>Title English</label><input name="screens[<?php echo e($index); ?>][title][en]" value="<?php echo e(data_get($screen, 'title.en')); ?>"></div>
                                    <div><label>Body Bangla</label><textarea name="screens[<?php echo e($index); ?>][body][bn]"><?php echo e(data_get($screen, 'body.bn')); ?></textarea></div>
                                    <div><label>Body English</label><textarea name="screens[<?php echo e($index); ?>][body][en]"><?php echo e(data_get($screen, 'body.en')); ?></textarea></div>
                                </div>
                            </div>
                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                    </div>
                </div>
            </section>

            <section class="landing-card landing-section-panel <?php echo e($activeSection === 'audience' ? 'is-active' : ''); ?>" id="audience" data-section-panel="audience">
                <div class="landing-card-head"><div><h3>Audience Section</h3><p>Who the system is for and the audience detail cards.</p></div><label class="section-toggle"><input type="hidden" name="audience[enabled]" value="0"><input type="checkbox" name="audience[enabled]" value="1" <?php if($value('audience.enabled', true)): echo 'checked'; endif; ?>> Enabled</label></div>
                <div class="landing-card-body">
                    <div class="landing-grid"><div><label>Icon</label><input name="audience[icon]" value="<?php echo e($value('audience.icon', '🏪')); ?>"></div><div></div><div><label>Title Bangla</label><input name="audience[title][bn]" value="<?php echo e($trans('audience.title', 'bn')); ?>"></div><div><label>Title English</label><input name="audience[title][en]" value="<?php echo e($trans('audience.title', 'en')); ?>"></div><div><label>Body Bangla</label><textarea name="audience[body][bn]"><?php echo e($trans('audience.body', 'bn')); ?></textarea></div><div><label>Body English</label><textarea name="audience[body][en]"><?php echo e($trans('audience.body', 'en')); ?></textarea></div></div>
                    <div class="muted-divider"></div>
                    <div class="landing-card-head" style="padding:0 0 14px;border:0;background:transparent"><div><h3>Audience Cards</h3></div><button type="button" class="button btn-outline btn-small" data-add="audiences">Add Audience</button></div>
                    <div class="repeat-list" data-repeater="audiences">
                        <?php $__currentLoopData = array_values($audiences); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $index => $audience): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                            <div class="repeat-card" data-repeat-item><div class="repeat-card-head"><div class="repeat-card-title">Audience Card</div><button type="button" class="button btn-ghost btn-small danger-link" data-remove-card>Remove</button></div><div class="landing-grid"><div><label>Title Bangla</label><input name="audiences[<?php echo e($index); ?>][title][bn]" value="<?php echo e(data_get($audience, 'title.bn')); ?>"></div><div><label>Title English</label><input name="audiences[<?php echo e($index); ?>][title][en]" value="<?php echo e(data_get($audience, 'title.en')); ?>"></div><div><label>Body Bangla</label><textarea name="audiences[<?php echo e($index); ?>][body][bn]"><?php echo e(data_get($audience, 'body.bn')); ?></textarea></div><div><label>Body English</label><textarea name="audiences[<?php echo e($index); ?>][body][en]"><?php echo e(data_get($audience, 'body.en')); ?></textarea></div></div></div>
                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                    </div>
                </div>
            </section>

            <section class="landing-card landing-section-panel <?php echo e($activeSection === 'pricing' ? 'is-active' : ''); ?>" id="pricing" data-section-panel="pricing">
                <div class="landing-card-head"><div><h3>Pricing & Packages</h3><p>Admins can add packages and package features without editing JSON.</p></div><label class="section-toggle"><input type="hidden" name="pricing[enabled]" value="0"><input type="checkbox" name="pricing[enabled]" value="1" <?php if($value('pricing.enabled', true)): echo 'checked'; endif; ?>> Enabled</label></div>
                <div class="landing-card-body">
                    <div class="landing-grid"><div><label>Mini Bangla</label><input name="pricing[mini][bn]" value="<?php echo e($trans('pricing.mini', 'bn')); ?>"></div><div><label>Mini English</label><input name="pricing[mini][en]" value="<?php echo e($trans('pricing.mini', 'en')); ?>"></div><div><label>Title Bangla</label><textarea name="pricing[title][bn]"><?php echo e($trans('pricing.title', 'bn')); ?></textarea></div><div><label>Title English</label><textarea name="pricing[title][en]"><?php echo e($trans('pricing.title', 'en')); ?></textarea></div><div><label>Subtitle Bangla</label><textarea name="pricing[subtitle][bn]"><?php echo e($trans('pricing.subtitle', 'bn')); ?></textarea></div><div><label>Subtitle English</label><textarea name="pricing[subtitle][en]"><?php echo e($trans('pricing.subtitle', 'en')); ?></textarea></div></div>
                    <div class="muted-divider"></div>
                    <div class="landing-card-head" style="padding:0 0 14px;border:0;background:transparent"><div><h3>Package Cards</h3><p>For features, write one feature per line in Bangla and English.</p></div><button type="button" class="button btn-outline btn-small" data-add="packages">Add Package</button></div>
                    <div class="repeat-list" data-repeater="packages">
                        <?php $__currentLoopData = array_values($packages); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $index => $package): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                            <?php ($pkgFeaturesBn = data_get($package, 'features_bn', $lines(data_get($package, 'features', []), 'bn'))); ?>
                            <?php ($pkgFeaturesEn = data_get($package, 'features_en', $lines(data_get($package, 'features', []), 'en'))); ?>
                            <div class="repeat-card" data-repeat-item><div class="repeat-card-head"><div class="repeat-card-title">Package Card</div><button type="button" class="button btn-ghost btn-small danger-link" data-remove-card>Remove</button></div><div class="landing-grid"><div><label>Name Bangla</label><input name="packages[<?php echo e($index); ?>][name][bn]" value="<?php echo e(data_get($package, 'name.bn')); ?>"></div><div><label>Name English</label><input name="packages[<?php echo e($index); ?>][name][en]" value="<?php echo e(data_get($package, 'name.en')); ?>"></div><div><label>Price</label><input name="packages[<?php echo e($index); ?>][price]" value="<?php echo e(data_get($package, 'price')); ?>"></div><div><label>Popular?</label><select name="packages[<?php echo e($index); ?>][popular]"><option value="0" <?php if(!data_get($package, 'popular')): echo 'selected'; endif; ?>>No</option><option value="1" <?php if(data_get($package, 'popular')): echo 'selected'; endif; ?>>Yes</option></select></div><div><label>Popular Tag Bangla</label><input name="packages[<?php echo e($index); ?>][tag][bn]" value="<?php echo e(data_get($package, 'tag.bn')); ?>"></div><div><label>Popular Tag English</label><input name="packages[<?php echo e($index); ?>][tag][en]" value="<?php echo e(data_get($package, 'tag.en')); ?>"></div><div><label>Suffix Bangla</label><input name="packages[<?php echo e($index); ?>][suffix][bn]" value="<?php echo e(data_get($package, 'suffix.bn')); ?>"></div><div><label>Suffix English</label><input name="packages[<?php echo e($index); ?>][suffix][en]" value="<?php echo e(data_get($package, 'suffix.en')); ?>"></div><div><label>Body Bangla</label><textarea name="packages[<?php echo e($index); ?>][body][bn]"><?php echo e(data_get($package, 'body.bn')); ?></textarea></div><div><label>Body English</label><textarea name="packages[<?php echo e($index); ?>][body][en]"><?php echo e(data_get($package, 'body.en')); ?></textarea></div><div><label>Features Bangla</label><textarea name="packages[<?php echo e($index); ?>][features_bn]" class="tall"><?php echo e($pkgFeaturesBn); ?></textarea></div><div><label>Features English</label><textarea name="packages[<?php echo e($index); ?>][features_en]" class="tall"><?php echo e($pkgFeaturesEn); ?></textarea></div><div><label>Button Bangla</label><input name="packages[<?php echo e($index); ?>][button][label][bn]" value="<?php echo e(data_get($package, 'button.label.bn')); ?>"></div><div><label>Button English</label><input name="packages[<?php echo e($index); ?>][button][label][en]" value="<?php echo e(data_get($package, 'button.label.en')); ?>"></div><div><label>Button Link</label><input name="packages[<?php echo e($index); ?>][button][href]" value="<?php echo e(data_get($package, 'button.href', '#contact')); ?>"></div><div><label>Button Style</label><select name="packages[<?php echo e($index); ?>][button][style]"><option value="primary" <?php if(data_get($package, 'button.style') === 'primary'): echo 'selected'; endif; ?>>Primary</option><option value="outline" <?php if(data_get($package, 'button.style') === 'outline'): echo 'selected'; endif; ?>>Outline</option><option value="dark" <?php if(data_get($package, 'button.style') === 'dark'): echo 'selected'; endif; ?>>Dark</option></select></div></div></div>
                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                    </div>
                    <div class="muted-divider"></div>
                    <div class="landing-card-head" style="padding:0 0 14px;border:0;background:transparent"><div><h3>Pricing Note Cards</h3></div><button type="button" class="button btn-outline btn-small" data-add="pricing_notes">Add Note</button></div>
                    <div class="repeat-list" data-repeater="pricing_notes">
                        <?php $__currentLoopData = array_values($pricingNotes); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $index => $note): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                            <div class="repeat-card" data-repeat-item><div class="repeat-card-head"><div class="repeat-card-title">Pricing Note</div><button type="button" class="button btn-ghost btn-small danger-link" data-remove-card>Remove</button></div><div class="landing-grid"><div><label>Title Bangla</label><input name="pricing_notes[<?php echo e($index); ?>][title][bn]" value="<?php echo e(data_get($note, 'title.bn')); ?>"></div><div><label>Title English</label><input name="pricing_notes[<?php echo e($index); ?>][title][en]" value="<?php echo e(data_get($note, 'title.en')); ?>"></div><div><label>Body Bangla</label><textarea name="pricing_notes[<?php echo e($index); ?>][body][bn]"><?php echo e(data_get($note, 'body.bn')); ?></textarea></div><div><label>Body English</label><textarea name="pricing_notes[<?php echo e($index); ?>][body][en]"><?php echo e(data_get($note, 'body.en')); ?></textarea></div><div><label>Button Bangla</label><input name="pricing_notes[<?php echo e($index); ?>][button][label][bn]" value="<?php echo e(data_get($note, 'button.label.bn')); ?>"></div><div><label>Button English</label><input name="pricing_notes[<?php echo e($index); ?>][button][label][en]" value="<?php echo e(data_get($note, 'button.label.en')); ?>"></div><div class="full"><label>Button Link</label><input name="pricing_notes[<?php echo e($index); ?>][button][href]" value="<?php echo e(data_get($note, 'button.href', '#contact')); ?>"></div></div></div>
                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                    </div>
                </div>
            </section>

            <section class="landing-card landing-section-panel <?php echo e($activeSection === 'testimonials' ? 'is-active' : ''); ?>" id="testimonials" data-section-panel="testimonials">
                <div class="landing-card-head"><div><h3>Testimonials</h3><p>Control testimonial section and customer quote cards.</p></div><label class="section-toggle"><input type="hidden" name="testimonials_section[enabled]" value="0"><input type="checkbox" name="testimonials_section[enabled]" value="1" <?php if($value('testimonials_section.enabled', true)): echo 'checked'; endif; ?>> Enabled</label></div>
                <div class="landing-card-body">
                    <div class="landing-grid"><div><label>Mini Bangla</label><input name="testimonials_section[mini][bn]" value="<?php echo e($trans('testimonials_section.mini', 'bn')); ?>"></div><div><label>Mini English</label><input name="testimonials_section[mini][en]" value="<?php echo e($trans('testimonials_section.mini', 'en')); ?>"></div><div><label>Title Bangla</label><input name="testimonials_section[title][bn]" value="<?php echo e($trans('testimonials_section.title', 'bn')); ?>"></div><div><label>Title English</label><input name="testimonials_section[title][en]" value="<?php echo e($trans('testimonials_section.title', 'en')); ?>"></div></div>
                    <div class="muted-divider"></div>
                    <div class="landing-card-head" style="padding:0 0 14px;border:0;background:transparent"><div><h3>Testimonial Cards</h3></div><button type="button" class="button btn-outline btn-small" data-add="testimonials">Add Testimonial</button></div>
                    <div class="repeat-list" data-repeater="testimonials">
                        <?php $__currentLoopData = array_values($testimonials); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $index => $testimonial): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                            <div class="repeat-card" data-repeat-item><div class="repeat-card-head"><div class="repeat-card-title">Testimonial</div><button type="button" class="button btn-ghost btn-small danger-link" data-remove-card>Remove</button></div><div class="landing-grid"><div><label>Name</label><input name="testimonials[<?php echo e($index); ?>][name]" value="<?php echo e(data_get($testimonial, 'name')); ?>"></div><div><label>Avatar Text</label><input name="testimonials[<?php echo e($index); ?>][avatar]" value="<?php echo e(data_get($testimonial, 'avatar')); ?>"></div><div><label>Role Bangla</label><input name="testimonials[<?php echo e($index); ?>][role][bn]" value="<?php echo e(data_get($testimonial, 'role.bn')); ?>"></div><div><label>Role English</label><input name="testimonials[<?php echo e($index); ?>][role][en]" value="<?php echo e(data_get($testimonial, 'role.en')); ?>"></div><div><label>Quote Bangla</label><textarea name="testimonials[<?php echo e($index); ?>][quote][bn]"><?php echo e(data_get($testimonial, 'quote.bn')); ?></textarea></div><div><label>Quote English</label><textarea name="testimonials[<?php echo e($index); ?>][quote][en]"><?php echo e(data_get($testimonial, 'quote.en')); ?></textarea></div></div></div>
                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                    </div>
                </div>
            </section>

            <section class="landing-card landing-section-panel <?php echo e($activeSection === 'faq' ? 'is-active' : ''); ?>" id="faq" data-section-panel="faq">
                <div class="landing-card-head"><div><h3>FAQ Section</h3><p>Add as many frequently asked questions as needed.</p></div><label class="section-toggle"><input type="hidden" name="faq_section[enabled]" value="0"><input type="checkbox" name="faq_section[enabled]" value="1" <?php if($value('faq_section.enabled', true)): echo 'checked'; endif; ?>> Enabled</label></div>
                <div class="landing-card-body">
                    <div class="landing-grid"><div><label>Mini Bangla</label><input name="faq_section[mini][bn]" value="<?php echo e($trans('faq_section.mini', 'bn')); ?>"></div><div><label>Mini English</label><input name="faq_section[mini][en]" value="<?php echo e($trans('faq_section.mini', 'en')); ?>"></div><div><label>Title Bangla</label><input name="faq_section[title][bn]" value="<?php echo e($trans('faq_section.title', 'bn')); ?>"></div><div><label>Title English</label><input name="faq_section[title][en]" value="<?php echo e($trans('faq_section.title', 'en')); ?>"></div></div>
                    <div class="muted-divider"></div>
                    <div class="landing-card-head" style="padding:0 0 14px;border:0;background:transparent"><div><h3>FAQ Cards</h3></div><button type="button" class="button btn-outline btn-small" data-add="faqs">Add FAQ</button></div>
                    <div class="repeat-list" data-repeater="faqs">
                        <?php $__currentLoopData = array_values($faqs); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $index => $faq): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                            <div class="repeat-card" data-repeat-item><div class="repeat-card-head"><div class="repeat-card-title">FAQ</div><button type="button" class="button btn-ghost btn-small danger-link" data-remove-card>Remove</button></div><div class="landing-grid"><div><label>Question Bangla</label><input name="faqs[<?php echo e($index); ?>][question][bn]" value="<?php echo e(data_get($faq, 'question.bn')); ?>"></div><div><label>Question English</label><input name="faqs[<?php echo e($index); ?>][question][en]" value="<?php echo e(data_get($faq, 'question.en')); ?>"></div><div><label>Answer Bangla</label><textarea name="faqs[<?php echo e($index); ?>][answer][bn]"><?php echo e(data_get($faq, 'answer.bn')); ?></textarea></div><div><label>Answer English</label><textarea name="faqs[<?php echo e($index); ?>][answer][en]"><?php echo e(data_get($faq, 'answer.en')); ?></textarea></div><div class="full"><label class="section-toggle"><input type="hidden" name="faqs[<?php echo e($index); ?>][open]" value="0"><input type="checkbox" name="faqs[<?php echo e($index); ?>][open]" value="1" <?php if(data_get($faq, 'open')): echo 'checked'; endif; ?>> Open by default</label></div></div></div>
                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                    </div>
                </div>
            </section>

            <section class="landing-card landing-section-panel <?php echo e($activeSection === 'contact' ? 'is-active' : ''); ?>" id="contact" data-section-panel="contact">
                <div class="landing-card-head"><div><h3>Contact & Demo Form</h3><p>Manage public contact details and form labels.</p></div><label class="section-toggle"><input type="hidden" name="contact[enabled]" value="0"><input type="checkbox" name="contact[enabled]" value="1" <?php if($value('contact.enabled', true)): echo 'checked'; endif; ?>> Enabled</label></div>
                <div class="landing-card-body landing-grid">
                    <div><label>Title Bangla</label><input name="contact[title][bn]" value="<?php echo e($trans('contact.title', 'bn')); ?>"></div><div><label>Title English</label><input name="contact[title][en]" value="<?php echo e($trans('contact.title', 'en')); ?>"></div>
                    <div><label>Body Bangla</label><textarea name="contact[body][bn]"><?php echo e($trans('contact.body', 'bn')); ?></textarea></div><div><label>Body English</label><textarea name="contact[body][en]"><?php echo e($trans('contact.body', 'en')); ?></textarea></div>
                    <div><label>Phone</label><input name="contact[phone]" value="<?php echo e($value('contact.phone')); ?>"></div><div><label>Email</label><input type="email" name="contact[email]" value="<?php echo e($value('contact.email')); ?>"></div>
                    <div><label>Phone Note Bangla</label><input name="contact[phone_note][bn]" value="<?php echo e($trans('contact.phone_note', 'bn')); ?>"></div><div><label>Phone Note English</label><input name="contact[phone_note][en]" value="<?php echo e($trans('contact.phone_note', 'en')); ?>"></div>
                    <div><label>Email Note Bangla</label><input name="contact[email_note][bn]" value="<?php echo e($trans('contact.email_note', 'bn')); ?>"></div><div><label>Email Note English</label><input name="contact[email_note][en]" value="<?php echo e($trans('contact.email_note', 'en')); ?>"></div>
                    <div class="full"><div class="code-help">These labels control the public contact/demo area. Phone clicks open WhatsApp, and email clicks open the visitor's email app.</div></div>
                    <div><label>Name Placeholder Bangla</label><input name="contact[form][name][bn]" value="<?php echo e($trans('contact.form.name', 'bn')); ?>"></div><div><label>Name Placeholder English</label><input name="contact[form][name][en]" value="<?php echo e($trans('contact.form.name', 'en')); ?>"></div>
                    <div><label>Business Placeholder Bangla</label><input name="contact[form][business_name][bn]" value="<?php echo e($trans('contact.form.business_name', 'bn')); ?>"></div><div><label>Business Placeholder English</label><input name="contact[form][business_name][en]" value="<?php echo e($trans('contact.form.business_name', 'en')); ?>"></div>
                    <div><label>Mobile Placeholder Bangla</label><input name="contact[form][mobile][bn]" value="<?php echo e($trans('contact.form.mobile', 'bn')); ?>"></div><div><label>Mobile Placeholder English</label><input name="contact[form][mobile][en]" value="<?php echo e($trans('contact.form.mobile', 'en')); ?>"></div>
                    <div><label>Email Placeholder Bangla</label><input name="contact[form][email][bn]" value="<?php echo e($trans('contact.form.email', 'bn')); ?>"></div><div><label>Email Placeholder English</label><input name="contact[form][email][en]" value="<?php echo e($trans('contact.form.email', 'en')); ?>"></div>
                    <div><label>Message Placeholder Bangla</label><textarea name="contact[form][message][bn]"><?php echo e($trans('contact.form.message', 'bn')); ?></textarea></div><div><label>Message Placeholder English</label><textarea name="contact[form][message][en]"><?php echo e($trans('contact.form.message', 'en')); ?></textarea></div>
                    <div><label>Submit Button Bangla</label><input name="contact[form][button][bn]" value="<?php echo e($trans('contact.form.button', 'bn')); ?>"></div><div><label>Submit Button English</label><input name="contact[form][button][en]" value="<?php echo e($trans('contact.form.button', 'en')); ?>"></div>
                    <div><label>Success Message Bangla</label><input name="contact[form][success][bn]" value="<?php echo e($trans('contact.form.success', 'bn')); ?>"></div><div><label>Success Message English</label><input name="contact[form][success][en]" value="<?php echo e($trans('contact.form.success', 'en')); ?>"></div>
                </div>
            </section>

            <section class="landing-card landing-section-panel <?php echo e($activeSection === 'footer' ? 'is-active' : ''); ?>" id="footer" data-section-panel="footer">
                <div class="landing-card-head"><div><h3>Footer</h3><p>Control footer descriptive text.</p></div></div>
                <div class="landing-card-body landing-grid">
                    <div><label>Footer Text Bangla</label><textarea name="footer[text][bn]"><?php echo e($trans('footer.text', 'bn')); ?></textarea></div>
                    <div><label>Footer Text English</label><textarea name="footer[text][en]"><?php echo e($trans('footer.text', 'en')); ?></textarea></div>
                </div>
            </section>

            <div class="form-actions sticky-actions">
                <button type="submit" class="button btn-primary">Save Landing Page</button>
                <a href="<?php echo e(route('landing.show', ['preview' => 1])); ?>" target="_blank" class="button btn-outline">Preview</a>
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
            <form method="POST" action="<?php echo e(route('landing-admin.reset')); ?>" onsubmit="return confirm('Reset landing page to default HisebGhor content?')" data-frontend-form>
                <?php echo csrf_field(); ?>
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
            <?php $__empty_1 = true; $__currentLoopData = $inquiries; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $inquiry): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
                <tr>
                    <td><?php echo e($inquiry->created_at?->format('d M Y')); ?></td><td class="strong"><?php echo e($inquiry->name); ?></td><td><?php echo e($inquiry->business_name ?: '—'); ?></td><td><?php echo e($inquiry->mobile ?: '—'); ?></td><td><?php echo e($inquiry->email ?: '—'); ?></td><td style="min-width:260px;white-space:normal"><?php echo e($inquiry->message ?: '—'); ?></td>
                    <td><form method="POST" action="<?php echo e(route('landing-admin.inquiries.update', $inquiry)); ?>" data-frontend-form><?php echo csrf_field(); ?> <?php echo method_field('PUT'); ?><select name="status" onchange="this.form.submit()" style="min-width:140px"><?php $__currentLoopData = $statuses; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $status): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><option value="<?php echo e($status); ?>" <?php if($inquiry->status === $status): echo 'selected'; endif; ?>><?php echo e($status); ?></option><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?></select></form></td>
                    <td><form method="POST" action="<?php echo e(route('landing-admin.inquiries.destroy', $inquiry)); ?>" data-delete-form><?php echo csrf_field(); ?> <?php echo method_field('DELETE'); ?><button type="submit" class="button btn-ghost" onclick="return confirm('Delete this inquiry?')">Delete</button></form></td>
                </tr>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
                <tr data-empty="true"><td colspan="8" class="muted">No demo inquiry found yet.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<template data-template="nav_links"><div class="repeat-card" data-repeat-item><div class="repeat-card-head"><div class="repeat-card-title">Menu Item</div><button type="button" class="button btn-ghost btn-small danger-link" data-remove-card>Remove</button></div><div class="landing-grid three"><div><label>Bangla Label</label><input name="nav_links[__INDEX__][label][bn]"></div><div><label>English Label</label><input name="nav_links[__INDEX__][label][en]"></div><div><label>Link / Section ID</label><input name="nav_links[__INDEX__][href]" value="#contact"></div></div></div></template>
<template data-template="hero_buttons"><div class="repeat-card" data-repeat-item><div class="repeat-card-head"><div class="repeat-card-title">Hero Button</div><button type="button" class="button btn-ghost btn-small danger-link" data-remove-card>Remove</button></div><div class="landing-grid three"><div><label>Bangla Label</label><input name="hero[buttons][__INDEX__][label][bn]"></div><div><label>English Label</label><input name="hero[buttons][__INDEX__][label][en]"></div><div><label>Style</label><select name="hero[buttons][__INDEX__][style]"><option value="primary">Primary</option><option value="outline">Outline</option><option value="dark">Dark</option></select></div><div class="full"><label>Link</label><input name="hero[buttons][__INDEX__][href]" value="#contact"></div></div></div></template>
<template data-template="trust_items"><div class="repeat-card" data-repeat-item><div class="repeat-card-head"><div class="repeat-card-title">Trust Item</div><button type="button" class="button btn-ghost btn-small danger-link" data-remove-card>Remove</button></div><div class="landing-grid"><div><label>Bangla</label><input name="trust_items[__INDEX__][bn]"></div><div><label>English</label><input name="trust_items[__INDEX__][en]"></div></div></div></template>
<template data-template="why_cards"><div class="repeat-card" data-repeat-item><div class="repeat-card-head"><div class="repeat-card-title">Feature Card</div><button type="button" class="button btn-ghost btn-small danger-link" data-remove-card>Remove</button></div><div class="landing-grid"><div><label>Icon</label><input name="why_cards[__INDEX__][icon]" value="✓"></div><div></div><div><label>Title Bangla</label><input name="why_cards[__INDEX__][title][bn]"></div><div><label>Title English</label><input name="why_cards[__INDEX__][title][en]"></div><div><label>Body Bangla</label><textarea name="why_cards[__INDEX__][body][bn]"></textarea></div><div><label>Body English</label><textarea name="why_cards[__INDEX__][body][en]"></textarea></div></div></div></template>
<template data-template="screens"><div class="repeat-card" data-repeat-item><div class="repeat-card-head"><div class="repeat-card-title">Screen Card</div><button type="button" class="button btn-ghost btn-small danger-link" data-remove-card>Remove</button></div><div class="landing-grid"><div class="full image-admin-field"><label>White Preview Image <small>PNG, JPG, WEBP or GIF; max 4 MB</small></label><input type="file" name="screens[__INDEX__][image]" accept="image/*" data-optional="true" data-file-input><input type="hidden" name="screens[__INDEX__][image_path]" value=""><input type="hidden" name="screens[__INDEX__][image_name]" value=""><input type="text" class="image-name-display" value="" placeholder="No image uploaded for this card" readonly data-file-name-display data-current-name="" data-optional="true"><span class="hint">Upload the white browser/screen preview only. The dark outer card, title, and description are rendered separately.</span></div><div><label>Title Bangla</label><input name="screens[__INDEX__][title][bn]"></div><div><label>Title English</label><input name="screens[__INDEX__][title][en]"></div><div><label>Body Bangla</label><textarea name="screens[__INDEX__][body][bn]"></textarea></div><div><label>Body English</label><textarea name="screens[__INDEX__][body][en]"></textarea></div></div></div></template>
<template data-template="audiences"><div class="repeat-card" data-repeat-item><div class="repeat-card-head"><div class="repeat-card-title">Audience Card</div><button type="button" class="button btn-ghost btn-small danger-link" data-remove-card>Remove</button></div><div class="landing-grid"><div><label>Title Bangla</label><input name="audiences[__INDEX__][title][bn]"></div><div><label>Title English</label><input name="audiences[__INDEX__][title][en]"></div><div><label>Body Bangla</label><textarea name="audiences[__INDEX__][body][bn]"></textarea></div><div><label>Body English</label><textarea name="audiences[__INDEX__][body][en]"></textarea></div></div></div></template>
<template data-template="packages"><div class="repeat-card" data-repeat-item><div class="repeat-card-head"><div class="repeat-card-title">Package Card</div><button type="button" class="button btn-ghost btn-small danger-link" data-remove-card>Remove</button></div><div class="landing-grid"><div><label>Name Bangla</label><input name="packages[__INDEX__][name][bn]"></div><div><label>Name English</label><input name="packages[__INDEX__][name][en]"></div><div><label>Price</label><input name="packages[__INDEX__][price]"></div><div><label>Popular?</label><select name="packages[__INDEX__][popular]"><option value="0">No</option><option value="1">Yes</option></select></div><div><label>Popular Tag Bangla</label><input name="packages[__INDEX__][tag][bn]"></div><div><label>Popular Tag English</label><input name="packages[__INDEX__][tag][en]"></div><div><label>Suffix Bangla</label><input name="packages[__INDEX__][suffix][bn]"></div><div><label>Suffix English</label><input name="packages[__INDEX__][suffix][en]"></div><div><label>Body Bangla</label><textarea name="packages[__INDEX__][body][bn]"></textarea></div><div><label>Body English</label><textarea name="packages[__INDEX__][body][en]"></textarea></div><div><label>Features Bangla</label><textarea name="packages[__INDEX__][features_bn]" class="tall"></textarea></div><div><label>Features English</label><textarea name="packages[__INDEX__][features_en]" class="tall"></textarea></div><div><label>Button Bangla</label><input name="packages[__INDEX__][button][label][bn]"></div><div><label>Button English</label><input name="packages[__INDEX__][button][label][en]"></div><div><label>Button Link</label><input name="packages[__INDEX__][button][href]" value="#contact"></div><div><label>Button Style</label><select name="packages[__INDEX__][button][style]"><option value="primary">Primary</option><option value="outline" selected>Outline</option><option value="dark">Dark</option></select></div></div></div></template>
<template data-template="pricing_notes"><div class="repeat-card" data-repeat-item><div class="repeat-card-head"><div class="repeat-card-title">Pricing Note</div><button type="button" class="button btn-ghost btn-small danger-link" data-remove-card>Remove</button></div><div class="landing-grid"><div><label>Title Bangla</label><input name="pricing_notes[__INDEX__][title][bn]"></div><div><label>Title English</label><input name="pricing_notes[__INDEX__][title][en]"></div><div><label>Body Bangla</label><textarea name="pricing_notes[__INDEX__][body][bn]"></textarea></div><div><label>Body English</label><textarea name="pricing_notes[__INDEX__][body][en]"></textarea></div><div><label>Button Bangla</label><input name="pricing_notes[__INDEX__][button][label][bn]"></div><div><label>Button English</label><input name="pricing_notes[__INDEX__][button][label][en]"></div><div class="full"><label>Button Link</label><input name="pricing_notes[__INDEX__][button][href]" value="#contact"></div></div></div></template>
<template data-template="testimonials"><div class="repeat-card" data-repeat-item><div class="repeat-card-head"><div class="repeat-card-title">Testimonial</div><button type="button" class="button btn-ghost btn-small danger-link" data-remove-card>Remove</button></div><div class="landing-grid"><div><label>Name</label><input name="testimonials[__INDEX__][name]"></div><div><label>Avatar Text</label><input name="testimonials[__INDEX__][avatar]"></div><div><label>Role Bangla</label><input name="testimonials[__INDEX__][role][bn]"></div><div><label>Role English</label><input name="testimonials[__INDEX__][role][en]"></div><div><label>Quote Bangla</label><textarea name="testimonials[__INDEX__][quote][bn]"></textarea></div><div><label>Quote English</label><textarea name="testimonials[__INDEX__][quote][en]"></textarea></div></div></div></template>
<template data-template="faqs"><div class="repeat-card" data-repeat-item><div class="repeat-card-head"><div class="repeat-card-title">FAQ</div><button type="button" class="button btn-ghost btn-small danger-link" data-remove-card>Remove</button></div><div class="landing-grid"><div><label>Question Bangla</label><input name="faqs[__INDEX__][question][bn]"></div><div><label>Question English</label><input name="faqs[__INDEX__][question][en]"></div><div><label>Answer Bangla</label><textarea name="faqs[__INDEX__][answer][bn]"></textarea></div><div><label>Answer English</label><textarea name="faqs[__INDEX__][answer][en]"></textarea></div><div class="full"><label class="section-toggle"><input type="hidden" name="faqs[__INDEX__][open]" value="0"><input type="checkbox" name="faqs[__INDEX__][open]" value="1"> Open by default</label></div></div></div></template>
<?php $__env->stopSection(); ?>

<?php $__env->startPush('scripts'); ?>
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
        });

        form.addEventListener('submit', function (event) {
            if (!validateLandingForm(form)) {
                event.preventDefault();
                event.stopPropagation();
            }
        });
    }

    const params = new URLSearchParams(window.location.search);
    const initialSection = params.get('section') || (window.location.hash ? window.location.hash.substring(1) : '<?php echo e($activeSection); ?>');
    activateSection(initialSection, false);
})();
</script>
<?php $__env->stopPush(); ?>

<?php echo $__env->make('layouts.landing-admin', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH /Applications/XAMPP/xamppfiles/htdocs/laravel/project_work/resources/views/landing/admin/edit.blade.php ENDPATH**/ ?>