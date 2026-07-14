<?php

namespace App\Models\Feed;

use App\Models\Company;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FeedBusinessTrackingDefaultAssignment extends Model
{
    public const SOURCE_TYPES = [
        'feed_item' => 'Inventory Item',
        'warehouse' => 'Warehouse',
        'manual' => 'Manual Source',
    ];

    protected $fillable = [
        'company_id',
        'source_type',
        'source_id',
        'source_label',
        'business_area',
        'business_tracking_unit_id',
        'allow_override',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'allow_override' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function trackingUnit(): BelongsTo
    {
        return $this->belongsTo(FeedBusinessTrackingUnit::class, 'business_tracking_unit_id');
    }

    public function getSourceTypeLabelAttribute(): string
    {
        return self::SOURCE_TYPES[$this->source_type] ?? ucfirst(str_replace('_', ' ', (string) $this->source_type));
    }
}
