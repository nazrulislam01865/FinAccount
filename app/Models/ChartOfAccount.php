<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ChartOfAccount extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'company_id',
        'account_code',
        'account_name',
        'account_level',
        'account_type_id',
        'normal_balance',
        'parent_id',
        'is_cash_bank',
        'posting_allowed',
        'opening_balance',
        'description',
        'status',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'is_cash_bank' => 'boolean',
        'posting_allowed' => 'boolean',
        'opening_balance' => 'decimal:2',
    ];

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function accountType()
    {
        return $this->belongsTo(AccountType::class);
    }

    public function parent()
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children()
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    public function getDisplayNameAttribute(): string
    {
        return trim($this->account_code . ' - ' . $this->account_name);
    }
    public function debitMappingRules()
    {
        return $this->hasMany(LedgerMappingRule::class, 'debit_account_id');
    }

    public function creditMappingRules()
    {
        return $this->hasMany(LedgerMappingRule::class, 'credit_account_id');
    }
    public function openingBalances()
    {
        return $this->hasMany(OpeningBalance::class, 'account_id');
    }
    public function voucherDetails()
    {
        return $this->hasMany(VoucherDetail::class, 'account_id');
    }
}
