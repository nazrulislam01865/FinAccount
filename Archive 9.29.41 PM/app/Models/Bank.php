<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Bank extends Model
{
    public function scopeActive($query)
    {
        return $query->where('status', 'Active');
    }

    protected $fillable = ['bank_name', 'short_name', 'country', 'status', 'sort_order'];
}
