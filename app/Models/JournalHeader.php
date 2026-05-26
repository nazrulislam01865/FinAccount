<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class JournalHeader extends Model
{
    public const STATUS_DRAFT = 'Draft';
    public const STATUS_SUBMITTED = 'Submitted';
    public const STATUS_POSTED = 'Posted';
    public const STATUS_CANCELLED = 'Cancelled';
    public const STATUS_REVERSED = 'Reversed';

    protected $fillable = [
        'company_id',
        'financial_year_id',
        'voucher_header_id',
        'journal_no',
        'voucher_number',
        'voucher_type',
        'source_type',
        'journal_date',
        'transaction_head_id',
        'party_id',
        'amount',
        'total_debit',
        'total_credit',
        'status',
        'narration',
        'created_by',
        'submitted_by',
        'approved_by',
        'posted_by',
        'submitted_at',
        'approved_at',
        'posted_at',
    ];

    protected $casts = [
        'journal_date' => 'date',
        'amount' => 'decimal:2',
        'total_debit' => 'decimal:2',
        'total_credit' => 'decimal:2',
        'submitted_at' => 'datetime',
        'approved_at' => 'datetime',
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

    public function voucherHeader()
    {
        return $this->belongsTo(VoucherHeader::class);
    }

    public function transactionHead()
    {
        return $this->belongsTo(TransactionHead::class);
    }

    public function party()
    {
        return $this->belongsTo(Party::class);
    }

    public function lines()
    {
        return $this->hasMany(JournalLine::class);
    }

    public function getIsBalancedAttribute(): bool
    {
        return round((float) $this->total_debit, 2) === round((float) $this->total_credit, 2);
    }
}
