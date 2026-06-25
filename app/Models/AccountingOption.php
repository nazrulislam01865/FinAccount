<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * @property array<string, mixed>|null $metadata
 */
class AccountingOption extends Model
{
    public const GROUP_ACCOUNT_TYPE = 'account_type';
    public const GROUP_NORMAL_BALANCE = 'normal_balance';
    public const GROUP_MONEY_ACCOUNT_KIND = 'money_account_kind';
    public const GROUP_PARTY_TYPE = 'party_type';
    public const GROUP_RULE_PARTY_TYPE = 'rule_party_type';
    public const GROUP_TRANSACTION_CATEGORY = 'transaction_category';
    public const GROUP_SETTLEMENT_TYPE = 'settlement_type';
    public const GROUP_ACCOUNTING_SOURCE = 'accounting_source';

    protected $fillable = [
        'option_group',
        'value',
        'label',
        'sort_order',
        'metadata',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
            'metadata' => 'array',
            'is_active' => 'boolean',
        ];
    }

    /**
     * @param Builder<AccountingOption> $query
     * @return Builder<AccountingOption>
     */
    public function scopeForGroup(Builder $query, string $group): Builder
    {
        return $query->where('option_group', $group);
    }

    /**
     * @param Builder<AccountingOption> $query
     * @return Builder<AccountingOption>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }
}
