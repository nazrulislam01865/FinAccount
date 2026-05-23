<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class TransactionHead extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'company_id',
        'head_code',
        'name',
        'nature',
        'category',
        'default_party_type_id',
        'default_primary_ledger_id',
        'default_movement',
        'payment_method_required',
        'party_required_mode',
        'transaction_screen',
        'is_system_default',
        'is_user_selectable',
        'sort_order',
        'linked_accounting_rule_code',
        'requires_party',
        'requires_reference',
        'description',
        'help_text',
        'developer_note',
        'status',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'requires_party' => 'boolean',
        'requires_reference' => 'boolean',
        'payment_method_required' => 'boolean',
        'is_system_default' => 'boolean',
        'is_user_selectable' => 'boolean',
        'sort_order' => 'integer',
    ];

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function defaultPartyType()
    {
        return $this->belongsTo(PartyType::class, 'default_party_type_id');
    }

    public function defaultPrimaryLedger()
    {
        return $this->belongsTo(ChartOfAccount::class, 'default_primary_ledger_id');
    }

    public function settlementTypes()
    {
        return $this->belongsToMany(
            SettlementType::class,
            'settlement_type_transaction_head',
            'transaction_head_id',
            'settlement_type_id'
        )->withTimestamps();
    }
    public function ledgerMappingRules()
    {
        return $this->hasMany(LedgerMappingRule::class);
    }
    public function vouchers()
    {
        return $this->hasMany(VoucherHeader::class);
    }
}
