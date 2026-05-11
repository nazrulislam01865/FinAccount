<?php

namespace App\Support;

use App\Models\Party;

class PartyCodeGenerator
{
    public static function next(): string
    {
        $last = Party::withTrashed()->orderByDesc('id')->value('party_code');
        $next = 1;

        if ($last && preg_match('/P-(\d+)/', $last, $matches)) {
            $next = ((int) $matches[1]) + 1;
        }

        return 'P-' . str_pad((string) $next, 5, '0', STR_PAD_LEFT);
    }
}
