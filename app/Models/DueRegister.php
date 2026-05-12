<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DueRegister extends Model
{
    protected $table = 'due_register';

    protected $fillable = [
        'voucher_header_id',
        'party_id',
        'account_id',
        'due_type',
        'movement',
        'amount',
        'balance_effect',
        'status',
        'due_date',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'balance_effect' => 'decimal:2',
        'due_date' => 'date',
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
