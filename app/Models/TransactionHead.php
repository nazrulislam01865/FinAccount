<?php

namespace App\Models;

use App\Support\TransactionTypes;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TransactionHead extends Model
{
    protected $fillable = [
        'company_id', 'accounting_rule_id', 'posting_account_id',
        'code', 'name', 'category', 'allowed_settlements', 'party_type', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'allowed_settlements' => 'array',
            'is_active' => 'boolean',
        ];
    }

    public function accountingRule(): BelongsTo
    {
        return $this->belongsTo(AccountingRule::class);
    }


    public function accountingRules(): HasMany
    {
        return $this->hasMany(AccountingRule::class);
    }

    public function postingAccount(): BelongsTo
    {
        return $this->belongsTo(ChartOfAccount::class, 'posting_account_id');
    }

    /** @return array<int, string> */
    public function allowedSettlementCodes(): array
    {
        if ($this->allowed_settlements) {
            return $this->allowed_settlements;
        }

        $option = AccountingOption::query()
            ->forGroup(AccountingOption::GROUP_TRANSACTION_CATEGORY)
            ->where('value', (string) $this->category)
            ->first();

        if ($option) {
            $definition = TransactionTypes::configuredDefinition(
                (string) $this->category,
                is_array($option->metadata) ? $option->metadata : [],
                $option->label,
            );

            return $definition['allowed_settlements'] ?: [TransactionTypes::CASH];
        }

        return TransactionTypes::allowedSettlements((string) $this->category);
    }

    public function allowsSettlement(string $settlementType): bool
    {
        return in_array($settlementType, $this->allowedSettlementCodes(), true);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }
}
