<?php

namespace App\Models\Feed;

use App\Models\Company;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FeedBusinessTrackingSetting extends Model
{
    protected $fillable = [
        'company_id',
        'require_farm_tracking',
        'allow_mixed_businesses',
        'allow_shared_allocation',
        'track_production_cycle',
    ];

    protected function casts(): array
    {
        return [
            'require_farm_tracking' => 'boolean',
            'allow_mixed_businesses' => 'boolean',
            'allow_shared_allocation' => 'boolean',
            'track_production_cycle' => 'boolean',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }
}
