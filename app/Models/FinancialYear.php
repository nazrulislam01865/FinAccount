<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FinancialYear extends Model
{
    public const STATUS_OPEN = 'open';
    public const STATUS_CLOSED = 'closed';

    protected $fillable = [
        'company_id', 'name', 'start_date', 'end_date', 'lock_date',
        'is_active', 'is_current', 'status', 'created_by', 'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'company_id' => 'integer',
            'start_date' => 'date',
            'end_date' => 'date',
            'lock_date' => 'date',
            'is_active' => 'boolean',
            'is_current' => 'boolean',
        ];
    }

    public static function statusOptions(): array
    {
        return [
            self::STATUS_OPEN => 'Open',
            self::STATUS_CLOSED => 'Closed',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function scopeForCompany(Builder $query, int $companyId): Builder
    {
        return $query->where('company_id', $companyId);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function containsDate(string $date): bool
    {
        return $this->start_date?->lte($date) && $this->end_date?->gte($date);
    }
}
