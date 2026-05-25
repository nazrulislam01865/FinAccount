<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ApprovalLog extends Model
{
    protected $fillable = [
        'company_id',
        'approval_workflow_id',
        'voucher_header_id',
        'approval_level',
        'action',
        'remarks',
        'acted_by',
        'acted_at',
    ];

    protected $casts = [
        'approval_level' => 'integer',
        'acted_at' => 'datetime',
    ];

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function workflow()
    {
        return $this->belongsTo(ApprovalWorkflow::class, 'approval_workflow_id');
    }

    public function voucher()
    {
        return $this->belongsTo(VoucherHeader::class, 'voucher_header_id');
    }

    public function actor()
    {
        return $this->belongsTo(User::class, 'acted_by');
    }
}
