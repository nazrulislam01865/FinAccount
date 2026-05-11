<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PartyType extends Model
{
    public function scopeActive($query)
    {
        return $query->where('status', 'Active');
    }

    protected $fillable = ['name', 'code', 'default_ledger_account_id', 'status', 'sort_order'];

    public function defaultLedger()
    {
        return $this->belongsTo(ChartOfAccount::class, 'default_ledger_account_id');
    }
}
