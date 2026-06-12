<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class VoucherDetail extends Model
{
    protected $fillable = [
        'voucher_header_id',
        'company_id',
        'branch_id',
        'transaction_date',
        'line_no',
        'account_id',
        'party_id',
        'rule_line_id',
        'amount_source',
        'entry_type',
        'debit',
        'credit',
        'narration',
    ];

    protected $casts = [
        'debit' => 'decimal:2',
        'credit' => 'decimal:2',
        'transaction_date' => 'date',
    ];

    public function voucherHeader()
    {
        return $this->belongsTo(VoucherHeader::class);
    }

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function account()
    {
        return $this->belongsTo(ChartOfAccount::class)->withTrashed();
    }

    public function party()
    {
        return $this->belongsTo(Party::class);
    }

    public function accountingRuleLine()
    {
        return $this->belongsTo(AccountingRuleLine::class, 'rule_line_id');
    }

    public function journalLine()
    {
        return $this->hasOne(JournalLine::class);
    }
}

