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
        'Increase Advance Asset',
        'Decrease Advance Asset',
        'Increase Advance Liability',
        'Decrease Advance Liability',
    ];

    protected $fillable = [
        'company_id',
        'transaction_head_id',
        'settlement_type_id',
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
