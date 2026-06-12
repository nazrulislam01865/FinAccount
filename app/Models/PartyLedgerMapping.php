<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PartyLedgerMapping extends Model
{
    public const PURPOSE_RECEIVABLE = 'receivable';
    public const PURPOSE_PAYABLE = 'payable';
    public const PURPOSE_ADVANCE_PAID = 'advance_paid';
    public const PURPOSE_ADVANCE_RECEIVED = 'advance_received';
    public const PURPOSE_LOAN_PAYABLE = 'loan_payable';
    public const PURPOSE_SALARY_PAYABLE = 'salary_payable';
    public const PURPOSE_CAPITAL = 'capital';
    public const PURPOSE_GENERAL = 'general';

    public const PURPOSES = [
        self::PURPOSE_RECEIVABLE,
        self::PURPOSE_PAYABLE,
        self::PURPOSE_ADVANCE_PAID,
        self::PURPOSE_ADVANCE_RECEIVED,
        self::PURPOSE_LOAN_PAYABLE,
        self::PURPOSE_SALARY_PAYABLE,
        self::PURPOSE_CAPITAL,
        self::PURPOSE_GENERAL,
    ];

    protected $fillable = [
        'company_id',
        'party_id',
        'mapping_purpose',
        'chart_of_account_id',
        'status',
        'created_by',
        'updated_by',
    ];

    public function scopeActive($query)
    {
        return $query->where('status', 'Active');
    }

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function party()
    {
        return $this->belongsTo(Party::class);
    }

    public function ledger()
    {
        return $this->belongsTo(ChartOfAccount::class, 'chart_of_account_id')->withTrashed();
    }
}
