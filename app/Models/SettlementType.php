<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SettlementType extends Model
{
    protected $fillable = [
        'name',
        'code',
        'status',
        'sort_order',
    ];

    public function scopeActive($query)
    {
        return $query->where('status', 'Active');
    }

    public function transactionHeads()
    {
        return $this->belongsToMany(
            TransactionHead::class,
            'settlement_type_transaction_head',
            'settlement_type_id',
            'transaction_head_id'
        )->withTimestamps();
    }
}
