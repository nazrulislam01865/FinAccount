<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SettlementType extends Model
{
    public function scopeActive($query)
    {
        return $query->where('status', 'Active');
    }

    protected $fillable = ['name', 'code', 'status', 'sort_order'];

    public function transactionHeads()
    {
        return $this->belongsToMany(TransactionHead::class)->withTimestamps();
    }
}
