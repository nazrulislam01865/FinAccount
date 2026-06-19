<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Transaction extends Model
{
    public const SETTLEMENT_NORMAL = 'normal';
    public const SETTLEMENT_PARTIAL = 'partial';

    protected $fillable = [
        'uuid', 'company_id', 'transaction_head_id', 'money_account_id', 'party_id',
        'created_by', 'voucher_no', 'category', 'transaction_date', 'amount',
        'settlement_type', 'paid_amount', 'due_amount', 'due_date',
        'reference', 'description', 'request_token', 'status', 'posted_at',
    ];

    protected function casts(): array
    {
        return [
            'transaction_date' => 'date',
            'amount' => 'decimal:2',
            'paid_amount' => 'decimal:2',
            'due_amount' => 'decimal:2',
            'due_date' => 'date',
            'posted_at' => 'datetime',
        ];
    }

    public function transactionHead(): BelongsTo
    {
        return $this->belongsTo(TransactionHead::class);
    }

    public function moneyAccount(): BelongsTo
    {
        return $this->belongsTo(MoneyAccount::class);
    }

    public function party(): BelongsTo
    {
        return $this->belongsTo(Party::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function journalEntry(): HasOne
    {
        return $this->hasOne(JournalEntry::class);
    }


    public function salesInvoice(): HasOne
    {
        return $this->hasOne(SalesInvoice::class);
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(TransactionAttachment::class);
    }
}
