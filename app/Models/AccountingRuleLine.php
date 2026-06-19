<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AccountingRuleLine extends Model
{
    public const SIDE_DEBIT = 'debit';
    public const SIDE_CREDIT = 'credit';

    public const BASIS_TOTAL = 'total';
    public const BASIS_PAID = 'paid';
    public const BASIS_DUE = 'due';

    protected $fillable = [
        'accounting_rule_id',
        'line_side',
        'account_source',
        'amount_basis',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
        ];
    }

    public function accountingRule(): BelongsTo
    {
        return $this->belongsTo(AccountingRule::class);
    }
}
