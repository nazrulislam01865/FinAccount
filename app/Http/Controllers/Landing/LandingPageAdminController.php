<?php

namespace App\Http\Controllers\Landing;

use App\Http\Controllers\Concerns\RespondsToDelete;
use App\Http\Controllers\Controller;
use App\Models\LandingPageInquiry;
use App\Support\LandingPageContent;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Throwable;

class LandingPageAdminController extends Controller
{
    use RespondsToDelete;

    public function dashboard(Request $request): View
    {
        $record = LandingPageContent::record();
        $landing = LandingPageContent::current(true);

        $statusCounts = LandingPageInquiry::query()
            ->selectRaw('status, COUNT(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status')
            ->all();

        $totalInquiries = array_sum(array_map('intval', $statusCounts));
        $latestInquiries = LandingPageInquiry::query()
            ->latest()
            ->limit(8)
            ->get();

        $sections = [
            'hero' => data_get($landing, 'hero.enabled', true),
            'why' => data_get($landing, 'why.enabled', true),
            'features' => data_get($landing, 'features.enabled', true),
            'audience' => data_get($landing, 'audience.enabled', true),
            'pricing' => data_get($landing, 'pricing.enabled', true),
            'testimonials' => data_get($landing, 'testimonials_section.enabled', true),
            'faq' => data_get($landing, 'faq_section.enabled', true),
            'contact' => data_get($landing, 'contact.enabled', true),
        ];

        return view('landing.admin.dashboard', [
            'landing' => $landing,
            'isPublished' => $record?->is_published ?? true,
            'updatedBy' => $record?->updater,
            'updatedAt' => $record?->updated_at,
            'statusCounts' => $statusCounts,
            'totalInquiries' => $totalInquiries,
            'latestInquiries' => $latestInquiries,
            'enabledSections' => collect($sections)->filter()->count(),
            'totalSections' => count($sections),
            'statuses' => LandingPageInquiry::STATUSES,
        ]);
    }

    public function edit(Request $request): View
    {
        $record = LandingPageContent::record();
        $landing = LandingPageContent::current(true);

        $inquiries = LandingPageInquiry::query()
            ->latest()
            ->limit(50)
            ->get();

        return view('landing.admin.edit', [
            'landing' => $landing,
            'isPublished' => $record?->is_published ?? true,
            'updatedBy' => $record?->updater,
            'updatedAt' => $record?->updated_at,
            'inquiries' => $inquiries,
            'activeSection' => $this->landingSection($request->query('section', 'basic')),
            'statuses' => LandingPageInquiry::STATUSES,
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate(
            $this->landingValidationRules(),
            $this->landingValidationMessages(),
            $this->landingValidationAttributes()
        );

        $current = LandingPageContent::current(true);

        $content = [
            'meta' => [
                'title' => $this->text($validated['meta']['title'] ?? data_get($current, 'meta.title')),
                'description' => $this->text($validated['meta']['description'] ?? data_get($current, 'meta.description')),
                'default_lang' => ($validated['meta']['default_lang'] ?? 'bn') === 'en' ? 'en' : 'bn',
            ],
            'theme' => $this->theme($request, $current),
            'brand' => [
                'name' => $this->text(data_get($validated, 'brand.name', data_get($current, 'brand.name', 'HisebGhor'))),
                'logo_text' => $this->text(data_get($validated, 'brand.logo_text', data_get($current, 'brand.logo_text', 'হি'))),
                'tagline' => $this->translation($request->input('brand.tagline'), data_get($current, 'brand.tagline')),
            ],
            'nav_links' => $this->navLinks($request->input('nav_links', [])),
            'cta' => [
                'primary' => [
                    'label' => $this->translation($request->input('cta.primary.label'), data_get($current, 'cta.primary.label')),
                    'href' => $this->text($request->input('cta.primary.href', data_get($current, 'cta.primary.href', '#contact'))),
                ],
                'secondary' => [
                    'label' => $this->translation($request->input('cta.secondary.label'), data_get($current, 'cta.secondary.label')),
                    'href' => $this->text($request->input('cta.secondary.href', data_get($current, 'cta.secondary.href', '/login'))),
                ],
            ],
            'hero' => $this->hero($request, $current),
            'trust_items' => $this->translationList($request->input('trust_items', [])),
            'why' => $this->sectionText($request, 'why', $current, true),
            'why_cards' => $this->cards($request->input('why_cards', []), ['icon', 'title', 'body']),
            'features' => $this->sectionText($request, 'features', $current, true),
            'screens' => $this->screens($request->input('screens', [])),
            'audience' => $this->audienceSection($request, $current),
            'audiences' => $this->cards($request->input('audiences', []), ['title', 'body']),
            'pricing' => $this->sectionText($request, 'pricing', $current, true),
            'packages' => $this->packages($request->input('packages', [])),
            'pricing_notes' => $this->pricingNotes($request->input('pricing_notes', [])),
            'testimonials_section' => $this->sectionText($request, 'testimonials_section', $current, false),
            'testimonials' => $this->testimonials($request->input('testimonials', [])),
            'faq_section' => $this->sectionText($request, 'faq_section', $current, false),
            'faqs' => $this->faqs($request->input('faqs', [])),
            'contact' => $this->contact($request, $current),
            'footer' => [
                'text' => $this->translation($request->input('footer.text'), data_get($current, 'footer.text')),
            ],
        ];

        LandingPageContent::save($content, $request->boolean('is_published'), null);

        return redirect()
            ->route('landing-admin.edit', [
                'section' => $this->landingSection($request->input('active_section', $request->query('section', 'basic'))),
            ])
            ->with('status', 'Landing page updated successfully.');
    }

    public function reset(Request $request): RedirectResponse
    {
        LandingPageContent::reset(null);

        return redirect()
            ->route('landing-admin.edit', ['section' => 'basic'])
            ->with('status', 'Landing page reset to the HisebGhor default landing content.');
    }

    public function updateInquiry(Request $request, LandingPageInquiry $inquiry): RedirectResponse
    {
        $validated = $request->validate([
            'status' => ['required', Rule::in(LandingPageInquiry::STATUSES)],
        ]);

        $inquiry->update($validated);

        return back()->with('status', 'Landing inquiry status updated.');
    }

    public function destroyInquiry(Request $request, LandingPageInquiry $inquiry): JsonResponse|RedirectResponse
    {
        try {
            $inquiry->delete();
        } catch (Throwable $exception) {
            return $this->deleteFailure(
                $request,
                'landing-admin.edit',
                'This landing inquiry could not be deleted. Please try again.',
                $exception
            );
        }

        return $this->deleteSuccess(
            $request,
            'landing-admin.edit',
            'Landing inquiry deleted successfully.'
        );
    }

    private function landingValidationRules(): array
    {
        $requiredText = ['required', 'string'];
        $requiredShortText = ['required', 'string', 'max:120'];
        $requiredMediumText = ['required', 'string', 'max:220'];
        $requiredLongText = ['required', 'string', 'max:1200'];
        $requiredColor = ['required', 'string', 'max:40'];

        return [
            'active_section' => ['required', Rule::in(['basic', 'nav', 'hero', 'why', 'features', 'audience', 'pricing', 'testimonials', 'faq', 'contact', 'footer'])],
            'is_published' => ['required', 'boolean'],

            'meta.title' => $requiredMediumText,
            'meta.description' => ['required', 'string', 'max:500'],
            'meta.default_lang' => ['required', Rule::in(['bn', 'en'])],

            'brand.name' => ['required', 'string', 'max:120'],
            'brand.logo_text' => ['required', 'string', 'max:20'],
            'brand.tagline.bn' => ['required', 'string', 'max:180'],
            'brand.tagline.en' => ['required', 'string', 'max:180'],

            'theme.green' => $requiredColor,
            'theme.green_dark' => $requiredColor,
            'theme.green_soft' => $requiredColor,
            'theme.blue' => $requiredColor,
            'theme.gold' => $requiredColor,
            'theme.ink' => $requiredColor,
            'theme.muted' => $requiredColor,
            'theme.bg' => $requiredColor,

            'nav_links' => ['required', 'array', 'min:1'],
            'nav_links.*.label.bn' => $requiredShortText,
            'nav_links.*.label.en' => $requiredShortText,
            'nav_links.*.href' => ['required', 'string', 'max:220'],

            'hero.enabled' => ['required', 'boolean'],
            'hero.eyebrow.bn' => $requiredMediumText,
            'hero.eyebrow.en' => $requiredMediumText,
            'hero.title.bn' => $requiredMediumText,
            'hero.title.en' => $requiredMediumText,
            'hero.subtitle.bn' => $requiredLongText,
            'hero.subtitle.en' => $requiredLongText,
            'hero.buttons' => ['required', 'array', 'min:1'],
            'hero.buttons.*.label.bn' => $requiredShortText,
            'hero.buttons.*.label.en' => $requiredShortText,
            'hero.buttons.*.style' => ['required', Rule::in(['primary', 'outline', 'dark'])],
            'hero.buttons.*.href' => ['required', 'string', 'max:220'],
            'trust_items' => ['required', 'array', 'min:1'],
            'trust_items.*.bn' => $requiredShortText,
            'trust_items.*.en' => $requiredShortText,
            'hero.dashboard.title.bn' => $requiredShortText,
            'hero.dashboard.title.en' => $requiredShortText,
            'hero.dashboard.subtitle.bn' => $requiredShortText,
            'hero.dashboard.subtitle.en' => $requiredShortText,
            'hero.dashboard.chip.bn' => $requiredShortText,
            'hero.dashboard.chip.en' => $requiredShortText,
            'hero.dashboard.stats' => ['required', 'array', 'min:1'],
            'hero.dashboard.stats.*.label.bn' => $requiredShortText,
            'hero.dashboard.stats.*.label.en' => $requiredShortText,
            'hero.dashboard.stats.*.value' => $requiredShortText,
            'hero.dashboard.rows' => ['required', 'array', 'min:1'],
            'hero.dashboard.rows.*.name.bn' => $requiredShortText,
            'hero.dashboard.rows.*.name.en' => $requiredShortText,
            'hero.dashboard.rows.*.debit' => $requiredShortText,
            'hero.dashboard.rows.*.credit' => $requiredShortText,

            'why.enabled' => ['required', 'boolean'],
            'why.mini.bn' => $requiredShortText,
            'why.mini.en' => $requiredShortText,
            'why.title.bn' => $requiredLongText,
            'why.title.en' => $requiredLongText,
            'why.subtitle.bn' => $requiredLongText,
            'why.subtitle.en' => $requiredLongText,
            'why_cards' => ['required', 'array', 'min:1'],
            'why_cards.*.icon' => ['required', 'string', 'max:20'],
            'why_cards.*.title.bn' => $requiredShortText,
            'why_cards.*.title.en' => $requiredShortText,
            'why_cards.*.body.bn' => $requiredLongText,
            'why_cards.*.body.en' => $requiredLongText,

            'features.enabled' => ['required', 'boolean'],
            'features.mini.bn' => $requiredShortText,
            'features.mini.en' => $requiredShortText,
            'features.title.bn' => $requiredLongText,
            'features.title.en' => $requiredLongText,
            'features.subtitle.bn' => $requiredLongText,
            'features.subtitle.en' => $requiredLongText,
            'screens' => ['required', 'array', 'min:1'],
            'screens.*.badges_bn' => ['required', 'string', 'max:1000'],
            'screens.*.badges_en' => ['required', 'string', 'max:1000'],
            'screens.*.title.bn' => $requiredShortText,
            'screens.*.title.en' => $requiredShortText,
            'screens.*.body.bn' => $requiredLongText,
            'screens.*.body.en' => $requiredLongText,

            'audience.enabled' => ['required', 'boolean'],
            'audience.icon' => ['required', 'string', 'max:20'],
            'audience.title.bn' => $requiredShortText,
            'audience.title.en' => $requiredShortText,
            'audience.body.bn' => $requiredLongText,
            'audience.body.en' => $requiredLongText,
            'audiences' => ['required', 'array', 'min:1'],
            'audiences.*.title.bn' => $requiredShortText,
            'audiences.*.title.en' => $requiredShortText,
            'audiences.*.body.bn' => $requiredLongText,
            'audiences.*.body.en' => $requiredLongText,

            'pricing.enabled' => ['required', 'boolean'],
            'pricing.mini.bn' => $requiredShortText,
            'pricing.mini.en' => $requiredShortText,
            'pricing.title.bn' => $requiredLongText,
            'pricing.title.en' => $requiredLongText,
            'pricing.subtitle.bn' => $requiredLongText,
            'pricing.subtitle.en' => $requiredLongText,
            'packages' => ['required', 'array', 'min:1'],
            'packages.*.name.bn' => $requiredShortText,
            'packages.*.name.en' => $requiredShortText,
            'packages.*.price' => $requiredShortText,
            'packages.*.popular' => ['required', 'boolean'],
            'packages.*.tag.bn' => $requiredShortText,
            'packages.*.tag.en' => $requiredShortText,
            'packages.*.suffix.bn' => $requiredShortText,
            'packages.*.suffix.en' => $requiredShortText,
            'packages.*.body.bn' => $requiredLongText,
            'packages.*.body.en' => $requiredLongText,
            'packages.*.features_bn' => ['required', 'string', 'max:4000'],
            'packages.*.features_en' => ['required', 'string', 'max:4000'],
            'packages.*.button.label.bn' => $requiredShortText,
            'packages.*.button.label.en' => $requiredShortText,
            'packages.*.button.href' => ['required', 'string', 'max:220'],
            'packages.*.button.style' => ['required', Rule::in(['primary', 'outline', 'dark'])],
            'pricing_notes' => ['required', 'array', 'min:1'],
            'pricing_notes.*.title.bn' => $requiredShortText,
            'pricing_notes.*.title.en' => $requiredShortText,
            'pricing_notes.*.body.bn' => $requiredLongText,
            'pricing_notes.*.body.en' => $requiredLongText,
            'pricing_notes.*.button.label.bn' => $requiredShortText,
            'pricing_notes.*.button.label.en' => $requiredShortText,
            'pricing_notes.*.button.href' => ['required', 'string', 'max:220'],

            'testimonials_section.enabled' => ['required', 'boolean'],
            'testimonials_section.mini.bn' => $requiredShortText,
            'testimonials_section.mini.en' => $requiredShortText,
            'testimonials_section.title.bn' => $requiredLongText,
            'testimonials_section.title.en' => $requiredLongText,
            'testimonials' => ['required', 'array', 'min:1'],
            'testimonials.*.name' => $requiredShortText,
            'testimonials.*.avatar' => ['required', 'string', 'max:10'],
            'testimonials.*.role.bn' => $requiredShortText,
            'testimonials.*.role.en' => $requiredShortText,
            'testimonials.*.quote.bn' => $requiredLongText,
            'testimonials.*.quote.en' => $requiredLongText,

            'faq_section.enabled' => ['required', 'boolean'],
            'faq_section.mini.bn' => $requiredShortText,
            'faq_section.mini.en' => $requiredShortText,
            'faq_section.title.bn' => $requiredLongText,
            'faq_section.title.en' => $requiredLongText,
            'faqs' => ['required', 'array', 'min:1'],
            'faqs.*.question.bn' => $requiredLongText,
            'faqs.*.question.en' => $requiredLongText,
            'faqs.*.answer.bn' => $requiredLongText,
            'faqs.*.answer.en' => $requiredLongText,
            'faqs.*.open' => ['nullable', 'boolean'],

            'contact.enabled' => ['required', 'boolean'],
            'contact.title.bn' => $requiredShortText,
            'contact.title.en' => $requiredShortText,
            'contact.body.bn' => $requiredLongText,
            'contact.body.en' => $requiredLongText,
            'contact.phone' => ['required', 'string', 'max:40'],
            'contact.email' => ['required', 'email', 'max:160'],
            'contact.phone_note.bn' => $requiredShortText,
            'contact.phone_note.en' => $requiredShortText,
            'contact.email_note.bn' => $requiredShortText,
            'contact.email_note.en' => $requiredShortText,
            'contact.form.name.bn' => $requiredShortText,
            'contact.form.name.en' => $requiredShortText,
            'contact.form.business_name.bn' => $requiredShortText,
            'contact.form.business_name.en' => $requiredShortText,
            'contact.form.mobile.bn' => $requiredShortText,
            'contact.form.mobile.en' => $requiredShortText,
            'contact.form.email.bn' => $requiredShortText,
            'contact.form.email.en' => $requiredShortText,
            'contact.form.message.bn' => $requiredLongText,
            'contact.form.message.en' => $requiredLongText,
            'contact.form.button.bn' => $requiredShortText,
            'contact.form.button.en' => $requiredShortText,
            'contact.form.success.bn' => $requiredLongText,
            'contact.form.success.en' => $requiredLongText,

            'footer.text.bn' => $requiredLongText,
            'footer.text.en' => $requiredLongText,
        ];
    }

    private function landingValidationMessages(): array
    {
        return [
            '*.required' => ':attribute is required and cannot be empty.',
            '*.min' => ':attribute must contain at least one item.',
            '*.email' => ':attribute must be a valid email address.',
            '*.max' => ':attribute is too long.',
            '*.in' => ':attribute has an invalid option.',
            '*.boolean' => ':attribute has an invalid yes/no value.',
        ];
    }

    private function landingValidationAttributes(): array
    {
        return [
            'nav_links' => 'Navigation menu',
            'hero.buttons' => 'Hero buttons',
            'trust_items' => 'Trust items',
            'hero.dashboard.stats' => 'Dashboard counters',
            'hero.dashboard.rows' => 'Dashboard transaction rows',
            'why_cards' => 'Why section cards',
            'screens' => 'Feature screen cards',
            'audiences' => 'Audience cards',
            'packages' => 'Pricing packages',
            'pricing_notes' => 'Pricing note cards',
            'testimonials' => 'Testimonials',
            'faqs' => 'FAQ cards',
        ];
    }

    private function landingSection(?string $section): string
    {
        $allowed = ['basic', 'nav', 'hero', 'why', 'features', 'audience', 'pricing', 'testimonials', 'faq', 'contact', 'footer'];

        return in_array($section, $allowed, true) ? $section : 'basic';
    }

    private function theme(Request $request, array $current): array
    {
        $defaults = data_get(LandingPageContent::defaults(), 'theme', []);
        $theme = (array) $request->input('theme', []);

        return [
            'green' => $this->text($theme['green'] ?? data_get($current, 'theme.green', data_get($defaults, 'green'))),
            'green_dark' => $this->text($theme['green_dark'] ?? data_get($current, 'theme.green_dark', data_get($defaults, 'green_dark'))),
            'green_soft' => $this->text($theme['green_soft'] ?? data_get($current, 'theme.green_soft', data_get($defaults, 'green_soft'))),
            'blue' => $this->text($theme['blue'] ?? data_get($current, 'theme.blue', data_get($defaults, 'blue'))),
            'gold' => $this->text($theme['gold'] ?? data_get($current, 'theme.gold', data_get($defaults, 'gold'))),
            'ink' => $this->text($theme['ink'] ?? data_get($current, 'theme.ink', data_get($defaults, 'ink'))),
            'muted' => $this->text($theme['muted'] ?? data_get($current, 'theme.muted', data_get($defaults, 'muted'))),
            'bg' => $this->text($theme['bg'] ?? data_get($current, 'theme.bg', data_get($defaults, 'bg'))),
        ];
    }

    private function hero(Request $request, array $current): array
    {
        return [
            'enabled' => $request->boolean('hero.enabled'),
            'eyebrow' => $this->translation($request->input('hero.eyebrow'), data_get($current, 'hero.eyebrow')),
            'title' => $this->translation($request->input('hero.title'), data_get($current, 'hero.title')),
            'highlight' => $this->translation($request->input('hero.highlight'), data_get($current, 'hero.highlight')),
            'subtitle' => $this->translation($request->input('hero.subtitle'), data_get($current, 'hero.subtitle')),
            'buttons' => $this->heroButtons($request->input('hero.buttons', [])),
            'dashboard' => [
                'title' => $this->translation($request->input('hero.dashboard.title'), data_get($current, 'hero.dashboard.title')),
                'subtitle' => $this->translation($request->input('hero.dashboard.subtitle'), data_get($current, 'hero.dashboard.subtitle')),
                'chip' => $this->translation($request->input('hero.dashboard.chip'), data_get($current, 'hero.dashboard.chip')),
                'stats' => $this->dashboardStats($request->input('hero.dashboard.stats', [])),
                'rows' => $this->dashboardRows($request->input('hero.dashboard.rows', [])),
            ],
        ];
    }

    private function sectionText(Request $request, string $key, array $current, bool $hasSubtitle): array
    {
        $section = [
            'enabled' => $request->boolean($key.'.enabled'),
            'mini' => $this->translation($request->input($key.'.mini'), data_get($current, $key.'.mini')),
            'title' => $this->translation($request->input($key.'.title'), data_get($current, $key.'.title')),
        ];

        if ($hasSubtitle) {
            $section['subtitle'] = $this->translation($request->input($key.'.subtitle'), data_get($current, $key.'.subtitle'));
        }

        return $section;
    }

    private function audienceSection(Request $request, array $current): array
    {
        return [
            'enabled' => $request->boolean('audience.enabled'),
            'icon' => $this->text($request->input('audience.icon', data_get($current, 'audience.icon', '🏪'))),
            'title' => $this->translation($request->input('audience.title'), data_get($current, 'audience.title')),
            'body' => $this->translation($request->input('audience.body'), data_get($current, 'audience.body')),
        ];
    }

    private function contact(Request $request, array $current): array
    {
        return [
            'enabled' => $request->boolean('contact.enabled'),
            'title' => $this->translation($request->input('contact.title'), data_get($current, 'contact.title')),
            'body' => $this->translation($request->input('contact.body'), data_get($current, 'contact.body')),
            'phone' => $this->text($request->input('contact.phone', data_get($current, 'contact.phone'))),
            'email' => $this->text($request->input('contact.email', data_get($current, 'contact.email'))),
            'phone_note' => $this->translation($request->input('contact.phone_note'), data_get($current, 'contact.phone_note')),
            'email_note' => $this->translation($request->input('contact.email_note'), data_get($current, 'contact.email_note')),
            'form' => [
                'name' => $this->translation($request->input('contact.form.name'), data_get($current, 'contact.form.name')),
                'business_name' => $this->translation($request->input('contact.form.business_name'), data_get($current, 'contact.form.business_name')),
                'mobile' => $this->translation($request->input('contact.form.mobile'), data_get($current, 'contact.form.mobile')),
                'email' => $this->translation($request->input('contact.form.email'), data_get($current, 'contact.form.email')),
                'message' => $this->translation($request->input('contact.form.message'), data_get($current, 'contact.form.message')),
                'button' => $this->translation($request->input('contact.form.button'), data_get($current, 'contact.form.button')),
                'success' => $this->translation($request->input('contact.form.success'), data_get($current, 'contact.form.success')),
            ],
        ];
    }

    private function navLinks(array $rows): array
    {
        $links = [];

        foreach ($rows as $row) {
            $label = $this->translation($row['label'] ?? []);
            $href = $this->text($row['href'] ?? '#');

            if ($this->blankTranslation($label) && $href === '') {
                continue;
            }

            $links[] = [
                'label' => $label,
                'href' => $href !== '' ? $href : '#',
            ];
        }

        return $links;
    }

    private function heroButtons(array $rows): array
    {
        $buttons = [];

        foreach ($rows as $row) {
            $label = $this->translation($row['label'] ?? []);
            $href = $this->text($row['href'] ?? '#contact');

            if ($this->blankTranslation($label) && $href === '') {
                continue;
            }

            $buttons[] = [
                'style' => $this->allowedStyle($row['style'] ?? 'primary'),
                'label' => $label,
                'href' => $href !== '' ? $href : '#contact',
            ];
        }

        return $buttons;
    }

    private function translationList(array $rows): array
    {
        $items = [];

        foreach ($rows as $row) {
            $item = $this->translation($row);
            if (!$this->blankTranslation($item)) {
                $items[] = $item;
            }
        }

        return $items;
    }

    private function cards(array $rows, array $fields): array
    {
        $cards = [];

        foreach ($rows as $row) {
            $card = [];

            foreach ($fields as $field) {
                if ($field === 'icon') {
                    $card['icon'] = $this->text($row['icon'] ?? '✓');
                    continue;
                }

                $card[$field] = $this->translation($row[$field] ?? []);
            }

            if ($this->allCardFieldsBlank($card)) {
                continue;
            }

            $cards[] = $card;
        }

        return $cards;
    }

    private function screens(array $rows): array
    {
        $screens = [];

        foreach ($rows as $row) {
            $screen = [
                'badges' => $this->pairedLines($row['badges_bn'] ?? '', $row['badges_en'] ?? ''),
                'title' => $this->translation($row['title'] ?? []),
                'body' => $this->translation($row['body'] ?? []),
            ];

            if ($this->allCardFieldsBlank($screen)) {
                continue;
            }

            $screens[] = $screen;
        }

        return $screens;
    }

    private function dashboardStats(array $rows): array
    {
        $stats = [];

        foreach ($rows as $row) {
            $label = $this->translation($row['label'] ?? []);
            $value = $this->text($row['value'] ?? '');

            if ($this->blankTranslation($label) && $value === '') {
                continue;
            }

            $stats[] = ['label' => $label, 'value' => $value];
        }

        return $stats;
    }

    private function dashboardRows(array $rows): array
    {
        $items = [];

        foreach ($rows as $row) {
            $name = $this->translation($row['name'] ?? []);
            $debit = $this->text($row['debit'] ?? '');
            $credit = $this->text($row['credit'] ?? '');

            if ($this->blankTranslation($name) && $debit === '' && $credit === '') {
                continue;
            }

            $items[] = [
                'name' => $name,
                'debit' => $debit,
                'credit' => $credit,
            ];
        }

        return $items;
    }

    private function packages(array $rows): array
    {
        $packages = [];

        foreach ($rows as $row) {
            $package = [
                'name' => $this->translation($row['name'] ?? []),
                'popular' => !empty($row['popular']),
                'tag' => $this->translation($row['tag'] ?? []),
                'body' => $this->translation($row['body'] ?? []),
                'price' => $this->text($row['price'] ?? ''),
                'suffix' => $this->translation($row['suffix'] ?? []),
                'features' => $this->pairedLines($row['features_bn'] ?? '', $row['features_en'] ?? ''),
                'button' => [
                    'style' => $this->allowedStyle($row['button']['style'] ?? 'outline'),
                    'label' => $this->translation($row['button']['label'] ?? []),
                    'href' => $this->text($row['button']['href'] ?? '#contact'),
                ],
            ];

            if ($this->blankTranslation($package['name']) && $package['price'] === '' && $this->blankTranslation($package['body'])) {
                continue;
            }

            $packages[] = $package;
        }

        return $packages;
    }

    private function pricingNotes(array $rows): array
    {
        $notes = [];

        foreach ($rows as $row) {
            $note = [
                'title' => $this->translation($row['title'] ?? []),
                'body' => $this->translation($row['body'] ?? []),
                'button' => [
                    'label' => $this->translation($row['button']['label'] ?? []),
                    'href' => $this->text($row['button']['href'] ?? '#contact'),
                ],
            ];

            if ($this->blankTranslation($note['title']) && $this->blankTranslation($note['body'])) {
                continue;
            }

            $notes[] = $note;
        }

        return $notes;
    }

    private function testimonials(array $rows): array
    {
        $testimonials = [];

        foreach ($rows as $row) {
            $testimonial = [
                'quote' => $this->translation($row['quote'] ?? []),
                'name' => $this->text($row['name'] ?? ''),
                'role' => $this->translation($row['role'] ?? []),
                'avatar' => $this->text($row['avatar'] ?? ''),
            ];

            if ($testimonial['name'] === '' && $this->blankTranslation($testimonial['quote'])) {
                continue;
            }

            $testimonials[] = $testimonial;
        }

        return $testimonials;
    }

    private function faqs(array $rows): array
    {
        $faqs = [];

        foreach ($rows as $row) {
            $faq = [
                'question' => $this->translation($row['question'] ?? []),
                'answer' => $this->translation($row['answer'] ?? []),
                'open' => !empty($row['open']),
            ];

            if ($this->blankTranslation($faq['question']) && $this->blankTranslation($faq['answer'])) {
                continue;
            }

            $faqs[] = $faq;
        }

        return $faqs;
    }

    private function pairedLines(string|array|null $bnText, string|array|null $enText): array
    {
        $bnLines = is_array($bnText) ? $bnText : preg_split('/\r\n|\r|\n/', (string) $bnText);
        $enLines = is_array($enText) ? $enText : preg_split('/\r\n|\r|\n/', (string) $enText);
        $count = max(count($bnLines ?: []), count($enLines ?: []));
        $items = [];

        for ($i = 0; $i < $count; $i++) {
            $bn = $this->text($bnLines[$i] ?? '');
            $en = $this->text($enLines[$i] ?? '');

            if ($bn === '' && $en === '') {
                continue;
            }

            $items[] = ['bn' => $bn, 'en' => $en];
        }

        return $items;
    }

    private function translation(array|string|null $value, array|string|null $fallback = null): array
    {
        if (!is_array($value)) {
            $value = [];
        }

        if (!is_array($fallback)) {
            $fallback = ['bn' => (string) ($fallback ?? ''), 'en' => (string) ($fallback ?? '')];
        }

        return [
            'bn' => $this->text($value['bn'] ?? data_get($fallback, 'bn', '')),
            'en' => $this->text($value['en'] ?? data_get($fallback, 'en', '')),
        ];
    }

    private function text(mixed $value): string
    {
        return trim((string) ($value ?? ''));
    }

    private function allowedStyle(string $style): string
    {
        return in_array($style, ['primary', 'outline', 'dark'], true) ? $style : 'primary';
    }

    private function blankTranslation(array $value): bool
    {
        return $this->text($value['bn'] ?? '') === '' && $this->text($value['en'] ?? '') === '';
    }

    private function allCardFieldsBlank(array $card): bool
    {
        foreach ($card as $key => $value) {
            if ($key === 'icon' && $this->text($value) === '✓') {
                continue;
            }

            if (is_array($value)) {
                if (array_is_list($value) && count($value) > 0) {
                    return false;
                }

                if (!$this->blankTranslation($value)) {
                    return false;
                }

                continue;
            }

            if ($this->text($value) !== '') {
                return false;
            }
        }

        return true;
    }
}
