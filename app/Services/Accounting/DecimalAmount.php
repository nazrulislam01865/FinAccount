<?php

namespace App\Services\Accounting;

class DecimalAmount
{
    public function normalize(int|float|string $value): string
    {
        $amount = trim((string) $value);

        if (str_contains(strtolower($amount), 'e')) {
            return number_format((float) $amount, 2, '.', '');
        }

        [$whole, $decimal] = array_pad(explode('.', $amount, 2), 2, '');
        $whole = ltrim($whole, '+');
        $decimal = substr(str_pad($decimal, 2, '0'), 0, 2);

        return ($whole === '' ? '0' : $whole).'.'.$decimal;
    }
}
