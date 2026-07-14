<?php

namespace App\Models\Feed;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FeedStockBalance extends Model
{
    protected $fillable = ['company_id', 'feed_item_id', 'tracking_unit_id', 'quantity', 'average_cost'];

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:4',
            'average_cost' => 'decimal:6',
        ];
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(FeedItem::class, 'feed_item_id');
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(FeedWarehouse::class, 'tracking_unit_id');
    }
}
