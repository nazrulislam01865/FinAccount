<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class FinancialYear extends Model
{
    use SoftDeletes;

    public const STATUS_OPEN = 'Open';
    public const STATUS_CLOSED = 'Closed';
    public const STATUS_LOCKED = 'Locked';

    public const STATUSES = [
        self::STATUS_OPEN,
        self::STATUS_CLOSED,
        self::STATUS_LOCKED,
    ];

    protected $fillable = [
        'company_id',
        'name',
        'start_date',
        'end_date',
        'lock_date',
        'is_active',
        'is_current',
        'status',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'lock_date' => 'date',
        'is_active' => 'boolean',
        'is_current' => 'boolean',
    ];

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function openingBalances()
    {
        return $this->hasMany(OpeningBalance::class);
    }

    public function scopeOpenForPosting(Builder $query): Builder
    {
        return $query->whereIn('status', [self::STATUS_OPEN, 'Active'])
            ->where(function (Builder $where) {
                $where->where('is_active', true)
                    ->orWhere('is_current', true);
            });
    }

    public function isOpenForPosting(): bool
    {
        return in_array($this->status, [self::STATUS_OPEN, 'Active'], true);
    }

    public function getDisplayNameAttribute(): string
    {
        return $this->start_date?->format('d/m/Y') . ' - ' . $this->end_date?->format('d/m/Y');
    }
}
