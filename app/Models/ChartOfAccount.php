<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ChartOfAccount extends Model
{
    protected $fillable = [
        'company_id',
        'parent_id',
        'level',
        'code',
        'name',
        'type',
        'normal_balance',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'level' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('code');
    }

    public function scopePostingAccounts(Builder $query): Builder
    {
        return $query->where('level', 3);
    }

    public function journalLines(): HasMany
    {
        return $this->hasMany(JournalLine::class);
    }

    public function moneyAccounts(): HasMany
    {
        return $this->hasMany(MoneyAccount::class);
    }

    public function receivableParties(): HasMany
    {
        return $this->hasMany(Party::class, 'receivable_account_id');
    }

    public function payableParties(): HasMany
    {
        return $this->hasMany(Party::class, 'payable_account_id');
    }

    public function transactionHeads(): HasMany
    {
        return $this->hasMany(TransactionHead::class, 'posting_account_id');
    }
}
