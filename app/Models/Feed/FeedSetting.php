<?php

namespace App\Models\Feed;

use App\Models\ChartOfAccount;
use App\Models\Company;
use App\Models\TransactionHead;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FeedSetting extends Model
{
    protected $fillable = [
        'company_id', 'purchase_transaction_head_id', 'sale_transaction_head_id',
        'cogs_account_id', 'default_tracking_unit_id',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function purchaseTransactionHead(): BelongsTo
    {
        return $this->belongsTo(TransactionHead::class, 'purchase_transaction_head_id');
    }

    public function saleTransactionHead(): BelongsTo
    {
        return $this->belongsTo(TransactionHead::class, 'sale_transaction_head_id');
    }

    public function cogsAccount(): BelongsTo
    {
        return $this->belongsTo(ChartOfAccount::class, 'cogs_account_id');
    }

    public function defaultWarehouse(): BelongsTo
    {
        return $this->belongsTo(FeedWarehouse::class, 'default_tracking_unit_id');
    }
}
