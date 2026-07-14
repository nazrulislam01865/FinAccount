<?php

namespace App\Models\Feed;

use App\Models\Company;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class FeedBusinessArea extends Model
{
    public const DEFAULT_AREAS = [
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
        'code',
        'name',
        'unit_label',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public static function normalizeCode(string $value): string
    {
        $code = Str::slug(trim($value), '_');

        return $code !== '' ? $code : 'business_area';
    }

    public static function defaultMeta(string $code): array
    {
        return self::DEFAULT_AREAS[$code] ?? [
            'name' => Str::headline(str_replace(['_', '-'], ' ', $code)),
            'icon' => '◫',
            'unit_label' => 'Unit',
            'unit_types' => ['Unit', 'Batch / Production Cycle'],
        ];
    }

    public function unitTypes(): array
    {
        $defaultTypes = self::DEFAULT_AREAS[$this->code]['unit_types'] ?? null;

        if ($defaultTypes) {
            return $defaultTypes;
        }

        return array_values(array_unique(array_filter([
            $this->unit_label ?: 'Unit',
            'Batch / Production Cycle',
        ])));
    }

    public function toTrackingOption(): array
    {
        $meta = self::defaultMeta($this->code);

        return [
            'name' => $this->name,
            'icon' => $meta['icon'] ?? '◫',
            'unit_label' => $this->unit_label ?: ($meta['unit_label'] ?? 'Unit'),
            'unit_types' => $this->unitTypes(),
            'is_active' => $this->is_active,
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }
}
