<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class JournalLine extends Model
{
    protected $fillable = [
        'journal_header_id',
        'voucher_detail_id',
        'line_no',
        'ledger_id',
        'party_id',
        'branch_id',
        'rule_line_id',
        'amount_source',
        'entry_type',
        'debit_amount',
        'credit_amount',
        'line_narration',
    ];

    protected $casts = [
        'debit_amount' => 'decimal:2',
        'credit_amount' => 'decimal:2',
    ];

    public function journalHeader()
    {
        return $this->belongsTo(JournalHeader::class);
    }

    public function voucherDetail()
    {
        return $this->belongsTo(VoucherDetail::class);
    }

    public function ledger()
    {
        return $this->belongsTo(ChartOfAccount::class, 'ledger_id')->withTrashed();
    }

    public function party()
    {
        return $this->belongsTo(Party::class);
    }
}
