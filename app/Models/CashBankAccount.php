<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class CashBankAccount extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'company_id',
        'cash_bank_code',
        'cash_bank_name',
        'type',
        'linked_ledger_account_id',
        'bank_id',
        'bank_name',
        'branch_name',
        'account_number',
        'opening_balance',
        'usage_note',
        'status',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'opening_balance' => 'decimal:2',
    ];

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function linkedLedger()
    {
        return $this->belongsTo(ChartOfAccount::class, 'linked_ledger_account_id');
    }

    public function bank()
    {
        return $this->belongsTo(Bank::class);
    }
}
