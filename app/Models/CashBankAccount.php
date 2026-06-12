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

    protected static function booted(): void
    {
        static::updating(function (CashBankAccount $account): void {
            $originalCode = $account->getOriginal('cash_bank_code');
            $originalCompanyId = $account->getOriginal('company_id');

            if ($originalCode !== null
                && $originalCode !== ''
                && $account->isDirty('cash_bank_code')) {
                throw new \LogicException('Cash/Bank ID is immutable and cannot be changed.');
            }

            if ($account->exists && $account->isDirty('company_id')
                && (string) $originalCompanyId !== (string) $account->company_id) {
                throw new \LogicException('Cash/Bank account company ownership cannot be changed.');
            }
        });
    }

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
