<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class VoucherDetail extends Model
{
    protected $fillable = [
        'voucher_header_id',
        'line_no',
        'account_id',
        'party_id',
        'entry_type',
        'debit',
        'credit',
        'narration',
    ];

    protected $casts = [
        'debit' => 'decimal:2',
        'credit' => 'decimal:2',
    ];

    public function voucherHeader()
    {
        return $this->belongsTo(VoucherHeader::class);
    }

    public function account()
    {
        return $this->belongsTo(ChartOfAccount::class);
    }

    public function party()
    {
        return $this->belongsTo(Party::class);
    }
}
