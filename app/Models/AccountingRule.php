<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AccountingRule extends Model
{
    public const SOURCE_SELECTED_MONEY = 'selected_money';
    public const SOURCE_HEAD_ACCOUNT = 'head_account';
    public const SOURCE_PARTY_RECEIVABLE = 'party_receivable';
    public const SOURCE_PARTY_PAYABLE = 'party_payable';

    protected $fillable = [
        'company_id', 'code', 'name', 'category', 'debit_source', 'credit_source',
        'party_required', 'party_type', 'money_required', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'party_required' => 'boolean',
            'money_required' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function transactionHeads(): HasMany
    {
        return $this->hasMany(TransactionHead::class);
    }

    public function sourceLabel(string $source): string
    {
        return match ($source) {
            self::SOURCE_SELECTED_MONEY => 'Selected Money Account',
            self::SOURCE_HEAD_ACCOUNT => 'Transaction Head COA',
            self::SOURCE_PARTY_RECEIVABLE => 'Party Receivable COA',
            self::SOURCE_PARTY_PAYABLE => 'Party Payable COA',
            default => $source,
        };
    }
}
