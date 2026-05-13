<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Currency extends Model
{
    public function scopeActive($query)
    {
        return $query->where('status', 'Active');
    }

    protected $fillable = ['code', 'name', 'symbol', 'decimal_places', 'is_default', 'status', 'sort_order'];

    protected $casts = [
        'decimal_places' => 'integer',
        'is_default' => 'boolean',
        'sort_order' => 'integer',
    ];
}
