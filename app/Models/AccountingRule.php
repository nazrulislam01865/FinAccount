<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AccountingRule extends Model
{
    public const SOURCE_SELECTED_MONEY = 'selected_money';
    public const SOURCE_HEAD_ACCOUNT = 'head_account';
    public const SOURCE_PARTY_RECEIVABLE = 'party_receivable';
    public const SOURCE_PARTY_PAYABLE = 'party_payable';

    protected $fillable = [
        'company_id', 'transaction_head_id', 'code', 'name', 'category', 'settlement_type', 'debit_source', 'credit_source',
        'party_required', 'party_type', 'money_required', 'generates_invoice', 'invoice_title', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'party_required' => 'boolean',
            'money_required' => 'boolean',
            'generates_invoice' => 'boolean',
            'is_active' => 'boolean',
        ];
    }


    public function transactionHead(): BelongsTo
    {
        return $this->belongsTo(TransactionHead::class);
    }

    public function transactionHeads(): HasMany
    {
        return $this->hasMany(TransactionHead::class);
    }

    public function lines(): HasMany
    {
        return $this->hasMany(AccountingRuleLine::class)->orderBy('sort_order');
    }
}
