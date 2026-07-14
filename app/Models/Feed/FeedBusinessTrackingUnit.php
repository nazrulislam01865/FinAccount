<?php

namespace App\Models\Feed;

use App\Models\Company;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FeedBusinessTrackingUnit extends Model
{
    /**
     * Fallback metadata kept for compatibility with existing reports and seed data.
     * The active Business Area dropdown now comes from feed_business_areas.
     */
    public const BUSINESS_AREAS = FeedBusinessArea::DEFAULT_AREAS;

    protected $fillable = [
        'company_id',
        'parent_id',
        'business_area',
        'unit_type',
        'code',
        'name',
        'location',
        'responsible_person',
        'start_date',
        'description',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'is_active' => 'boolean',
        ];
    }

    public static function businessAreaKeys(): array
    {
        return array_keys(self::BUSINESS_AREAS);
    }

    public static function unitTypesFor(string $businessArea): array
    {
        return FeedBusinessArea::defaultMeta($businessArea)['unit_types'] ?? ['Unit', 'Batch / Production Cycle'];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('name');
    }

    public function defaultAssignments(): HasMany
    {
        return $this->hasMany(FeedBusinessTrackingDefaultAssignment::class, 'business_tracking_unit_id');
    }

    public function getBusinessNameAttribute(): string
    {
        return FeedBusinessArea::defaultMeta((string) $this->business_area)['name'] ?? ucfirst((string) $this->business_area);
    }

    public function getBusinessIconAttribute(): string
    {
        return FeedBusinessArea::defaultMeta((string) $this->business_area)['icon'] ?? '◫';
    }

    public function getUnitLabelAttribute(): string
    {
        return FeedBusinessArea::defaultMeta((string) $this->business_area)['unit_label'] ?? 'Unit';
    }
}
