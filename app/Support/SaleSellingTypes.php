<?php

namespace App\Support;

final class SaleSellingTypes
{
    public const FEED = 'feed';

    public const FISH = 'fish';

    public const CATTLE = 'cattle';

    public const VEGETABLE = 'vegetable';

    public const VEGETABLES = 'vegetables';

    public const OTHERS = 'others';

    /** @return array<string, string> */
    public static function labels(): array
    {
        return [
            self::FEED => 'Feed',
            self::FISH => 'Fish',
            self::CATTLE => 'Cattle',
            self::VEGETABLE => 'Vegetable',
            self::VEGETABLES => 'Vegetables',
            self::OTHERS => 'Others',
        ];
    }

    public static function normalize(mixed $value): ?string
    {
        $normalized = strtolower(trim((string) $value));

        if ($normalized === 'vegetable') {
            $normalized = self::VEGETABLES;
        }

        return $normalized !== '' ? $normalized : null;
    }


    public static function isOthers(mixed $value): bool
    {
        return self::normalize($value) === self::OTHERS;
    }

    public static function requiresWarehouse(mixed $value): bool
    {
        $normalized = self::normalize($value);

        return filled($normalized) && $normalized !== self::OTHERS;
    }

    public static function isSaleCategory(mixed $category): bool
    {
        return strcasecmp(trim((string) $category), TransactionTypes::SALE) === 0;
    }
}
