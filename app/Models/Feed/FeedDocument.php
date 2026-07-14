<?php

namespace App\Models\Feed;

use App\Models\Party;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FeedDocument extends Model
{
    public const TYPE_PURCHASE = 'PURCHASE';
    public const TYPE_SALE = 'SALE';

    protected $fillable = [
        'uuid', 'company_id', 'transaction_id', 'tracking_unit_id', 'party_id', 'created_by',
        'document_type', 'external_invoice_no', 'reference', 'cost_allocation',
        'subtotal', 'transport_cost', 'other_cost', 'delivery_charge', 'overall_discount',
        'total_amount', 'cogs_total',
    ];

    protected function casts(): array
    {
        return [
            'subtotal' => 'decimal:2',
            'transport_cost' => 'decimal:2',
            'other_cost' => 'decimal:2',
            'delivery_charge' => 'decimal:2',
            'overall_discount' => 'decimal:2',
            'total_amount' => 'decimal:2',
            'cogs_total' => 'decimal:2',
        ];
    }

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class);
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(FeedWarehouse::class, 'tracking_unit_id');
    }

    public function party(): BelongsTo
    {
        return $this->belongsTo(Party::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function lines(): HasMany
    {
        return $this->hasMany(FeedDocumentLine::class);
    }

    public function movements(): HasMany
    {
        return $this->hasMany(FeedStockMovement::class);
    }
}
