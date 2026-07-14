<?php

namespace App\Models\Feed;

use App\Models\Company;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FeedBusinessTrackingUnit extends Model
{
    public const BUSINESS_AREAS = [
        'cattle' => [
            'name' => 'Cattle',
            'icon' => '🐄',
            'unit_label' => 'Shed',
            'unit_types' => ['Shed', 'Batch / Herd'],
        ],
        'fish' => [
            'name' => 'Fish',
            'icon' => '🐟',
            'unit_label' => 'Pond',
            'unit_types' => ['Pond', 'Species / Cycle'],
        ],
        'vegetables' => [
            'name' => 'Vegetables',
            'icon' => '🥬',
            'unit_label' => 'Vegetable / Crop',
            'unit_types' => ['Vegetable / Crop', 'Plot / Field', 'Season / Cycle'],
        ],
    ];

    protected $fillable = [
        'company_id',
        'parent_id',
        'business_area',
        'unit_type',
        'code',
        'name',
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
        return self::BUSINESS_AREAS[$businessArea]['unit_types'] ?? [];
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
        return self::BUSINESS_AREAS[$this->business_area]['name'] ?? ucfirst((string) $this->business_area);
    }

    public function getBusinessIconAttribute(): string
    {
        return self::BUSINESS_AREAS[$this->business_area]['icon'] ?? '◫';
    }

    public function getUnitLabelAttribute(): string
    {
        return self::BUSINESS_AREAS[$this->business_area]['unit_label'] ?? 'Unit';
    }
}
