<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ApprovalWorkflow extends Model
{
    protected $fillable = [
        'company_id',
        'transaction_type',
        'transaction_head_id',
        'approval_required',
        'threshold_amount',
        'approver_role_id',
        'approval_level',
        'auto_approve_below_amount',
        'status',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'approval_required' => 'boolean',
        'auto_approve_below_amount' => 'boolean',
        'threshold_amount' => 'decimal:2',
        'approval_level' => 'integer',
    ];

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function transactionHead()
    {
        return $this->belongsTo(TransactionHead::class);
    }

    public function approverRole()
    {
        return $this->belongsTo(Role::class, 'approver_role_id');
    }

    public function logs()
    {
        return $this->hasMany(ApprovalLog::class);
    }
}
