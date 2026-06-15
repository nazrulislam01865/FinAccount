<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TransactionHead extends Model
{
    protected $fillable = [
        'company_id', 'accounting_rule_id', 'posting_account_id',
        'code', 'name', 'category', 'is_active',
    ];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function accountingRule(): BelongsTo
    {
        return $this->belongsTo(AccountingRule::class);
    }

    public function postingAccount(): BelongsTo
    {
        return $this->belongsTo(ChartOfAccount::class, 'posting_account_id');
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }
}
