<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class VoucherAttachment extends Model
{
    protected $fillable = [
        'voucher_header_id',
        'original_name',
        'file_path',
        'mime_type',
        'size_bytes',
        'created_by',
    ];

    public function voucherHeader()
    {
        return $this->belongsTo(VoucherHeader::class);
    }
}
