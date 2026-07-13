<?php

namespace App\Models\Feed;

use App\Models\Company;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FeedItem extends Model
{
    protected $fillable = [
        'company_id', 'code', 'name', 'category', 'brand', 'pack_size', 'base_unit',
        'default_purchase_price', 'default_sale_price', 'reorder_level',
        'track_batch', 'track_expiry', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'pack_size' => 'decimal:4',
            'default_purchase_price' => 'decimal:2',
            'default_sale_price' => 'decimal:2',
            'reorder_level' => 'decimal:4',
            'track_batch' => 'boolean',
            'track_expiry' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function balances(): HasMany
    {
        return $this->hasMany(FeedStockBalance::class);
    }

    public function movements(): HasMany
    {
        return $this->hasMany(FeedStockMovement::class);
    }
}
