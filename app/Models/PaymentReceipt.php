<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PaymentReceipt extends Model
{
    public const STATUS_ISSUED = 'issued';

    protected $fillable = [
        'uuid',
        'company_id',
        'transaction_id',
        'party_id',
        'receipt_no',
        'title',
        'receipt_date',
        'due_type',
        'amount',
        'previous_due_amount',
        'remaining_due_amount',
        'status',
        'party_snapshot',
        'company_snapshot',
    ];

    protected function casts(): array
    {
        return [
            'receipt_date' => 'date',
            'amount' => 'decimal:2',
            'previous_due_amount' => 'decimal:2',
            'remaining_due_amount' => 'decimal:2',
            'party_snapshot' => 'array',
            'company_snapshot' => 'array',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class);
    }

    public function party(): BelongsTo
    {
        return $this->belongsTo(Party::class);
    }
}
