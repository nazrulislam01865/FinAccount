<?php

namespace App\Support;

final class SaleSellingTypes
{
    public const FISH = 'fish';

    public const CATTLE = 'cattle';

    public const VEGETABLE = 'vegetable';

    public const OTHERS = 'others';

    /** @return array<string, string> */
    public static function labels(): array
    {
        return [
            self::FISH => 'Fish',
            self::CATTLE => 'Cattle',
            self::VEGETABLE => 'Vegetable',
            self::OTHERS => 'Others',
        ];
    }

    public static function normalize(mixed $value): ?string
    {
        $normalized = strtolower(trim((string) $value));

        return $normalized !== '' ? $normalized : null;
    }

    public static function requiresWarehouse(mixed $value): bool
    {
        return in_array(self::normalize($value), [
            self::FISH,
            self::CATTLE,
            self::VEGETABLE,
        ], true);
    }

    public static function isSaleCategory(mixed $category): bool
    {
        return strcasecmp(trim((string) $category), TransactionTypes::SALE) === 0;
    }
}
