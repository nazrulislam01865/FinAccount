<?php

namespace App\Models\Feed;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FeedDocumentLine extends Model
{
    protected $fillable = [
        'company_id', 'feed_document_id', 'feed_item_id', 'quantity', 'unit',
        'base_quantity', 'rate', 'discount', 'line_total', 'allocated_cost',
        'unit_cost', 'cogs_total', 'batch_no', 'expiry_date',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:4',
            'base_quantity' => 'decimal:4',
            'rate' => 'decimal:2',
            'discount' => 'decimal:2',
            'line_total' => 'decimal:2',
            'allocated_cost' => 'decimal:2',
            'unit_cost' => 'decimal:6',
            'cogs_total' => 'decimal:2',
            'expiry_date' => 'date',
        ];
    }

    public function document(): BelongsTo
    {
        return $this->belongsTo(FeedDocument::class, 'feed_document_id');
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(FeedItem::class, 'feed_item_id');
    }
}
