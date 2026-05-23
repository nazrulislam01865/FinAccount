<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class LedgerMappingRule extends Model
{
    use SoftDeletes;

    public const PARTY_EFFECTS = [
        'No Effect',
        'Increase Liability',
        'Decrease Liability',
        'Increase Receivable',
        'Decrease Receivable',
        'Increase Asset',
        'Decrease Asset',
        'Increase Advance Asset',
        'Decrease Advance Asset',
        'Increase Advance Liability',
        'Decrease Advance Liability',
    ];

    protected $fillable = [
        'company_id',
        'rule_code',
        'rule_name',
        'transaction_head_id',
        'settlement_type_id',
        'transaction_screen',
        'rule_trigger',
        'amount_required',
        'payment_method_required',
        'allowed_payment_method',
        'cash_bank_ledger_required',
        'party_required_mode',
        'party_sub_ledger_type',
        'other_required_input',
        'primary_ledger_source',
        'primary_ledger_id',
        'primary_ledger_movement',
        'primary_posting_side',
        'primary_explanation',
        'counter_ledger_source',
        'counter_selection_method',
        'fixed_counter_ledger_id',
        'allowed_counter_ledger_type',
        'counter_ledger_movement',
        'counter_posting_side',
        'counter_explanation',
        'debit_account_id',
        'credit_account_id',
        'party_ledger_effect',
        'auto_post',
        'description',
        'status',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'auto_post' => 'boolean',
        'amount_required' => 'boolean',
        'payment_method_required' => 'boolean',
        'cash_bank_ledger_required' => 'boolean',
    ];

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function transactionHead()
    {
        return $this->belongsTo(TransactionHead::class);
    }

    public function settlementType()
    {
        return $this->belongsTo(SettlementType::class);
    }

    public function primaryLedger()
    {
        return $this->belongsTo(ChartOfAccount::class, 'primary_ledger_id');
    }

    public function fixedCounterLedger()
    {
        return $this->belongsTo(ChartOfAccount::class, 'fixed_counter_ledger_id');
    }

    public function debitAccount()
    {
        return $this->belongsTo(ChartOfAccount::class, 'debit_account_id');
    }

    public function creditAccount()
    {
        return $this->belongsTo(ChartOfAccount::class, 'credit_account_id');
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'Active');
    }
}
