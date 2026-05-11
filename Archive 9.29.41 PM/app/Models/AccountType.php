<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AccountType extends Model
{
    public function scopeActive($query)
    {
        return $query->where('status', 'Active');
    }

    protected $fillable = ['name', 'code', 'normal_balance', 'status', 'sort_order'];
}
