<?php

namespace App\Support;

use Carbon\CarbonImmutable;

final class BackdatedTransactionWindow
{
    public const YEAR_COUNT = 3;

    public static function today(): CarbonImmutable
    {
        return CarbonImmutable::parse(now()->toDateString())->startOfDay();
    }

    public static function start(): CarbonImmutable
    {
        return self::today()->startOfYear()->subYears(self::YEAR_COUNT - 1);
    }

    public static function end(): CarbonImmutable
    {
        return self::today();
    }

    /** @return array<int, int> */
    public static function years(): array
    {
        $currentYear = (int) self::today()->format('Y');

        return range($currentYear, $currentYear - (self::YEAR_COUNT - 1));
    }

    public static function contains(CarbonImmutable $date): bool
    {
        $date = $date->startOfDay();

        return $date->betweenIncluded(self::start(), self::end());
    }

    public static function validationMessage(): string
    {
        return 'Backdated transactions are limited to the current year and the previous two years.';
    }
}
