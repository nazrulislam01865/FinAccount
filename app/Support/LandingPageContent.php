<?php

namespace App\Support;

use App\Models\LandingPageSetting;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Throwable;

class LandingPageContent
{
    public const CACHE_KEY = 'landing_page.homepage.v3';
    public const SETTING_KEY = 'homepage';

    public static function defaults(): array
    {
        return config('landing.defaults', []);
    }

    public static function record(): ?LandingPageSetting
    {
        try {
            return LandingPageSetting::query()->where('key', self::SETTING_KEY)->first();
        } catch (Throwable) {
            return null;
        }
    }

    public static function current(bool $fresh = false): array
    {
        if ($fresh) {
            Cache::forget(self::CACHE_KEY);
        }

        return Cache::remember(self::CACHE_KEY, now()->addSeconds((int) config('performance.cache.landing_page_ttl_seconds', 86400)), function () {
            $defaults = self::defaults();
            $record = self::record();

            if (!$record || !is_array($record->value)) {
                return $defaults;
            }

            return self::merge($defaults, $record->value);
        });
    }

    public static function isPublished(): bool
    {
        $record = self::record();

        return $record?->is_published ?? true;
    }

    public static function save(array $content, bool $isPublished, ?int $userId = null): LandingPageSetting
    {
        $record = LandingPageSetting::query()->firstOrNew(['key' => self::SETTING_KEY]);
        $record->value = self::merge(self::defaults(), $content);
        $record->is_published = $isPublished;
        $record->updated_by_id = $userId;
        $record->save();

        Cache::forget(self::CACHE_KEY);

        return $record;
    }

    public static function reset(?int $userId = null): LandingPageSetting
    {
        return self::save(self::defaults(), true, $userId);
    }

    public static function merge(array $defaults, array $content): array
    {
        $defaults = self::removeDeprecatedPackageRecommendationFields($defaults);
        $content = self::removeDeprecatedPackageRecommendationFields($content);

        $merged = array_replace_recursive($defaults, $content);

        foreach (['nav_links', 'trust_items', 'why_cards', 'screens', 'audiences', 'testimonials', 'faqs'] as $listKey) {
            if (Arr::has($content, $listKey) && is_array($content[$listKey])) {
                $merged[$listKey] = $content[$listKey];
            }
        }

        foreach (['packages', 'pricing_notes'] as $schemaListKey) {
            if (! Arr::has($content, $schemaListKey) || ! is_array($content[$schemaListKey])) {
                continue;
            }

            $defaultRows = is_array($defaults[$schemaListKey] ?? null) ? $defaults[$schemaListKey] : [];
            $submittedRows = array_values($content[$schemaListKey]);
            $legacySchema = false;

            foreach ($submittedRows as $row) {
                if (! is_array($row)) {
                    continue;
                }

                if ($schemaListKey === 'packages' && ! isset($row['fees'])) {
                    $legacySchema = true;
                    break;
                }

                if ($schemaListKey === 'pricing_notes' && (! isset($row['icon']) || isset($row['button']))) {
                    $legacySchema = true;
                    break;
                }
            }

            if ($legacySchema) {
                $merged[$schemaListKey] = $defaultRows;
                continue;
            }

            $merged[$schemaListKey] = [];

            foreach ($submittedRows as $index => $row) {
                if (! is_array($row)) {
                    continue;
                }

                $defaultRow = is_array($defaultRows[$index] ?? null) ? $defaultRows[$index] : [];
                $merged[$schemaListKey][] = array_replace_recursive($defaultRow, $row);
            }
        }

        return $merged;
    }

    private static function removeDeprecatedPackageRecommendationFields(array $content): array
    {
        if (! isset($content['packages']) || ! is_array($content['packages'])) {
            return $content;
        }

        $content['packages'] = array_map(static function ($package) {
            if (! is_array($package)) {
                return $package;
            }

            unset($package['popular'], $package['popular_label']);

            return $package;
        }, $content['packages']);

        return $content;
    }
}
