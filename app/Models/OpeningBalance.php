<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class OpeningBalance extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'company_id',
        'financial_year_id',
        'balance_date',
        'branch_location',
        'account_id',
        'party_id',
        'debit_opening',
        'credit_opening',
        'remarks',
        'status',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'debit_opening' => 'decimal:2',
        'credit_opening' => 'decimal:2',
        'balance_date' => 'date',
    ];

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function financialYear()
    {
        return $this->belongsTo(FinancialYear::class);
    }

    public function account()
    {
        return $this->belongsTo(ChartOfAccount::class, 'account_id');
    }

    public function party()
    {
        return $this->belongsTo(Party::class);
    }

    public function getNetBalanceAttribute(): float
    {
        return round((float) $this->debit_opening - (float) $this->credit_opening, 2);
    }
}
