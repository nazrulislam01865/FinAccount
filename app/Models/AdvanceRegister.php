<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AdvanceRegister extends Model
{
    protected $table = 'advance_register';

    protected $fillable = [
        'voucher_header_id',
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

    public function party()
    {
        return $this->belongsTo(Party::class);
    }

    public function account()
    {
        return $this->belongsTo(ChartOfAccount::class);
    }
}
