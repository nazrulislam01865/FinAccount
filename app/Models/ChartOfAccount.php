<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ChartOfAccount extends Model
{
    protected $fillable = ['company_id', 'code', 'name', 'type', 'normal_balance', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
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
