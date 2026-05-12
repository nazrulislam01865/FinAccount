<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class FinancialYear extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'company_id',
        'name',
        'start_date',
        'end_date',
        'is_active',
        'status',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'is_active' => 'boolean',
    ];

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function openingBalances()
    {
        return $this->hasMany(OpeningBalance::class);
    }

    public function getDisplayNameAttribute(): string
    {
        return $this->start_date?->format('d/m/Y') . ' - ' . $this->end_date?->format('d/m/Y');
    }
}
