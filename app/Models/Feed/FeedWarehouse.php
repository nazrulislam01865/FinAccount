<?php

namespace App\Models\Feed;

use App\Models\Company;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FeedWarehouse extends Model
{
    protected $fillable = ['company_id', 'code', 'name', 'location', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function balances(): HasMany
    {
        return $this->hasMany(FeedStockBalance::class, 'warehouse_id');
    }

    public function documents(): HasMany
    {
        return $this->hasMany(FeedDocument::class, 'warehouse_id');
    }
}
