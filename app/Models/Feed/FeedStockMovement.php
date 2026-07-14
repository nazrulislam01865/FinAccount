<?php

namespace App\Models\Feed;

use App\Models\Transaction;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FeedStockMovement extends Model
{
    public const TYPE_PURCHASE = 'PURCHASE';
    public const TYPE_SALE = 'SALE';

    protected $fillable = [
        'company_id', 'feed_document_id', 'transaction_id', 'feed_item_id', 'tracking_unit_id',
        'movement_type', 'movement_date', 'quantity_in', 'quantity_out', 'unit_cost',
        'total_value', 'quantity_before', 'quantity_after', 'average_cost_before',
        'average_cost_after', 'reference',
    ];

    protected function casts(): array
    {
        return [
            'movement_date' => 'date',
            'quantity_in' => 'decimal:4',
            'quantity_out' => 'decimal:4',
            'unit_cost' => 'decimal:6',
            'total_value' => 'decimal:2',
            'quantity_before' => 'decimal:4',
            'quantity_after' => 'decimal:4',
            'average_cost_before' => 'decimal:6',
            'average_cost_after' => 'decimal:6',
        ];
    }

    public function document(): BelongsTo
    {
        return $this->belongsTo(FeedDocument::class, 'feed_document_id');
    }

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class);
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
