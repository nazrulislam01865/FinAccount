<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TimeZone extends Model
{
    public function scopeActive($query)
    {
        return $query->where('status', 'Active');
    }

    protected $fillable = ['name', 'utc_offset', 'php_timezone', 'is_default', 'status', 'sort_order'];
}
