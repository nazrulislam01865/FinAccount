<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TransactionSaleLine extends Model
{
    protected $fillable = [
        'company_id',
        'transaction_id',
        'business_area',
        'item_name',
        'unit',
        'quantity',
        'rate',
        'discount',
        'line_total',
        'sequence',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:4',
            'rate' => 'decimal:2',
            'discount' => 'decimal:2',
            'line_total' => 'decimal:2',
        ];
    }

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class);
    }
}
