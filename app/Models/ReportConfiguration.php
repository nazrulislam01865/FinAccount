<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ReportConfiguration extends Model
{
    protected $fillable = [
        'report_key',
        'report_name',
        'role_id',
        'can_view',
        'can_export',
        'include_zero_balances',
        'include_inactive_accounts',
        'default_filters',
        'sort_order',
        'status',
    ];

    protected $casts = [
        'role_id' => 'integer',
        'can_view' => 'boolean',
        'can_export' => 'boolean',
        'include_zero_balances' => 'boolean',
        'include_inactive_accounts' => 'boolean',
        'default_filters' => 'array',
        'sort_order' => 'integer',
    ];

    public function role()
    {
        return $this->belongsTo(Role::class);
    }
}
