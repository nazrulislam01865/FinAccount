<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LandingPageSetting extends Model
{
    protected $fillable = [
        'key',
        'value',
        'is_published',
        'updated_by_id',
    ];

    protected $casts = [
        'value' => 'array',
        'is_published' => 'boolean',
    ];

    public function updater()
    {
        return $this->belongsTo(User::class, 'updated_by_id');
    }
}
