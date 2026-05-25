<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class VoucherHeader extends Model
{
    use SoftDeletes;

    public const STATUS_DRAFT = 'Draft';
    public const STATUS_PENDING_REVIEW = 'Pending Review';
    public const STATUS_POSTED = 'Posted';
    public const STATUS_CANCELLED = 'Cancelled';
    public const STATUS_REVERSED = 'Reversed';


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
        'lifecycle_state',
        'submitted_at',
        'submitted_by',
        'approved_at',
        'approved_by',
        'posted_at',
        'posted_by',
        'voided_at',
        'voided_by',
        'void_reason',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'voucher_date' => 'date',
        'amount' => 'decimal:2',
        'total_debit' => 'decimal:2',
        'total_credit' => 'decimal:2',
        'submitted_at' => 'datetime',
        'approved_at' => 'datetime',
        'posted_at' => 'datetime',
        'voided_at' => 'datetime',
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

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function submittedBy()
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    public function approvedBy()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function postedBy()
    {
        return $this->belongsTo(User::class, 'posted_by');
    }

    public function approvalLogs()
    {
        return $this->hasMany(ApprovalLog::class);
    }
}
