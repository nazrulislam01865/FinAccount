<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class AccountingRule extends Model
{
    use SoftDeletes;

    public const STATUSES = [
        'Draft',
        'Pending Review',
        'Active',
        'Inactive',
    ];

    public const PARTY_REQUIRED_MODES = [
        'No',
        'Yes',
        'Optional',
    ];

    protected $fillable = [
        'company_id',
        'legacy_ledger_mapping_rule_id',
        'rule_code',
        'rule_name',
        'transaction_head_id',
        'settlement_type_id',
        'transaction_screen',
        'rule_trigger',
        'amount_required',
        'party_required_mode',
        'party_type_id',
        'party_sub_ledger_type',
        'payment_method_required',
        'allowed_payment_methods',
        'cash_bank_ledger_required',
        'party_ledger_effect',
        'auto_post',
        'description',
        'status',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'amount_required' => 'boolean',
        'payment_method_required' => 'boolean',
        'allowed_payment_methods' => 'array',
        'cash_bank_ledger_required' => 'boolean',
        'auto_post' => 'boolean',
    ];

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function legacyLedgerMappingRule()
    {
        return $this->belongsTo(LedgerMappingRule::class, 'legacy_ledger_mapping_rule_id');
    }

    public function transactionHead()
    {
        return $this->belongsTo(TransactionHead::class);
    }

    public function settlementType()
    {
        return $this->belongsTo(SettlementType::class);
    }

    public function partyType()
    {
        return $this->belongsTo(PartyType::class);
    }

    public function lines()
    {
        return $this->hasMany(AccountingRuleLine::class)->orderBy('sort_order')->orderBy('id');
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'Active');
    }

    public function requiresParty(): bool
    {
        return in_array($this->party_required_mode, ['Yes', 'Required'], true);
    }

    public function allowsOptionalParty(): bool
    {
        return $this->party_required_mode === 'Optional';
    }
}
