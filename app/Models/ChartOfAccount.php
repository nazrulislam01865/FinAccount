<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ChartOfAccount extends Model
{
    use SoftDeletes;

    public const COA_LEVELS = [
        1 => 'Account Class',
        2 => 'Account Group',
        3 => 'Account Sub-Group',
        4 => 'Ledger Head / Posting Account',
    ];

    public const LEDGER_TYPES = [
        'Group',
        'Cash',
        'Bank',
        'Party Control',
        'Inventory',
        'Asset',
        'Loan',
        'Liability',
        'Equity',
        'Equity Contra',
        'Income',
        'Expense',
        'Other',
    ];

    protected $fillable = [
        'company_id',
        'account_code',
        'account_name',
        'account_level',
        'coa_level',
        'account_type_id',
        'account_group',
        'account_sub_group',
        'account_nature',
        'normal_balance',
        'parent_id',
        'is_cash_bank',
        'is_party_control',
        'party_type_id',
        'is_system_ledger',
        'is_user_selectable',
        'posting_allowed',
        'ledger_type',
        'opening_balance',
        'description',
        'example_usage',
        'status',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'coa_level' => 'integer',
        'is_cash_bank' => 'boolean',
        'is_party_control' => 'boolean',
        'is_system_ledger' => 'boolean',
        'is_user_selectable' => 'boolean',
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

    public function partyType()
    {
        return $this->belongsTo(PartyType::class);
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

    public function getLevelNameAttribute(): string
    {
        return self::COA_LEVELS[(int) ($this->coa_level ?: ($this->account_level === 'Ledger' ? 4 : 1))]
            ?? (string) ($this->account_level ?: 'Ledger');
    }

    public function getEffectiveCoaLevelAttribute(): int
    {
        return (int) ($this->coa_level ?: ($this->account_level === 'Ledger' ? 4 : 1));
    }

    public function scopePostingLedgers($query)
    {
        return $query->where('status', 'Active')
            ->where('posting_allowed', true)
            ->where(function ($nested) {
                $nested->where('coa_level', 4)
                    ->orWhere('account_level', 'Ledger');
            });
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

    public function journalLines()
    {
        return $this->hasMany(JournalLine::class, 'ledger_id');
    }
}

