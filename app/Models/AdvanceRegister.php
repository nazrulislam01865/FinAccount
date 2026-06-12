<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AdvanceRegister extends Model
{
    protected $table = 'advance_register';

    protected $fillable = [
        'voucher_header_id',
        'voucher_detail_id',
        'source_voucher_detail_id',
        'party_id',
        'account_id',
        'advance_type',
        'movement',
        'amount',
        'balance_effect',
        'status',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'balance_effect' => 'decimal:2',
    ];

    public function voucherHeader()
    {
        return $this->belongsTo(VoucherHeader::class);
    }

    public function voucherDetail()
    {
        return $this->belongsTo(VoucherDetail::class);
    }


    public function sourceVoucherDetail()
    {
        return $this->belongsTo(VoucherDetail::class, 'source_voucher_detail_id');
    }

    public function party()
    {
        return $this->belongsTo(Party::class);
    }

    public function account()
    {
        return $this->belongsTo(ChartOfAccount::class)->withTrashed();
    }
}
