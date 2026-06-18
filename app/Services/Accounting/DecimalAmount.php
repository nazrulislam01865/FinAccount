<?php

namespace App\Services\Accounting;

class DecimalAmount
{
    public function normalize(int|float|string $value, int $scale = 2): string
    {
        $scale = max(0, min(2, $scale));
        $amount = trim((string) $value);

        if (str_contains(strtolower($amount), 'e')) {
            return number_format((float) $amount, $scale, '.', '');
        }

        [$whole, $decimal] = array_pad(explode('.', $amount, 2), 2, '');
        $whole = ltrim($whole, '+');

        if ($scale === 0) {
            return $whole === '' ? '0' : $whole;
        }

        $decimal = substr(str_pad($decimal, $scale, '0'), 0, $scale);

        return ($whole === '' ? '0' : $whole).'.'.$decimal;
    }
}
