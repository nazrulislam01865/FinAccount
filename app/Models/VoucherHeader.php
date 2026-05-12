<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class VoucherHeader extends Model
{
    use SoftDeletes;

    public const STATUS_DRAFT = 'Draft';
    public const STATUS_POSTED = 'Posted';

    protected $fillable = [
        'company_id',
        'financial_year_id',
        'voucher_number',
        'voucher_type',
        'voucher_date',
        'transaction_head_id',
        'settlement_type_id',
        'party_id',
        'cash_bank_account_id',
        'amount',
        'total_debit',
        'total_credit',
        'party_ledger_effect',
        'cash_bank_effect',
        'reference',
        'notes',
        'status',
        'posted_at',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'voucher_date' => 'date',
        'amount' => 'decimal:2',
        'total_debit' => 'decimal:2',
        'total_credit' => 'decimal:2',
        'posted_at' => 'datetime',
    ];

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function financialYear()
    {
        return $this->belongsTo(FinancialYear::class);
    }

    public function transactionHead()
    {
        return $this->belongsTo(TransactionHead::class);
    }

    public function settlementType()
    {
        return $this->belongsTo(SettlementType::class);
    }

    public function party()
    {
        return $this->belongsTo(Party::class);
    }

    public function cashBankAccount()
    {
        return $this->belongsTo(CashBankAccount::class);
    }

    public function details()
    {
        return $this->hasMany(VoucherDetail::class);
    }

    public function attachments()
    {
        return $this->hasMany(VoucherAttachment::class);
    }
}
