<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;

class LedgerType extends Model
{
    /**
     * Ledger type codes that are part of the accounting engine's core classification.
     * These can be deactivated, but not deleted, because reports/rules depend on them.
     */
    public const PROTECTED_CODES = [
        'GROUP',
        'CASH',
        'BANK',
        'PARTY_CONTROL',
        'ASSET',
        'LIABILITY',
        'EQUITY',
        'INCOME',
        'EXPENSE',
    ];

    protected $fillable = [
        'name',
        'code',
        'description',
        'is_system',
        'sort_order',
        'status',
    ];

    protected $casts = [
        'is_system' => 'boolean',
        'sort_order' => 'integer',
    ];

    public static function activeNames(): array
    {
        if (! Schema::hasTable('ledger_types')) {
            return \App\Models\ChartOfAccount::LEDGER_TYPES;
        }

        $names = static::query()
            ->where('status', 'Active')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->pluck('name')
            ->all();

        return $names ?: \App\Models\ChartOfAccount::LEDGER_TYPES;
    }

    public function isProtectedSystemType(): bool
    {
        return in_array(strtoupper((string) $this->code), self::PROTECTED_CODES, true);
    }
}
