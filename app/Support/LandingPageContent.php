<?php

namespace App\Support;

use App\Models\LandingPageSetting;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Throwable;

class LandingPageContent
{
    public const CACHE_KEY = 'landing_page.homepage';
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
        $merged = array_replace_recursive($defaults, $content);

        foreach (['nav_links', 'trust_items', 'why_cards', 'screens', 'audiences', 'packages', 'pricing_notes', 'testimonials', 'faqs'] as $listKey) {
            if (Arr::has($content, $listKey) && is_array($content[$listKey])) {
                $merged[$listKey] = $content[$listKey];
            }
        }

        return $merged;
    }
}
