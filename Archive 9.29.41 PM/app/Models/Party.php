<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Party extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'company_id',
        'party_code',
        'party_name',
        'party_type_id',
        'mobile',
        'email',
        'address',
        'linked_ledger_account_id',
        'opening_balance',
        'opening_balance_type',
        'notes',
        'status',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'opening_balance' => 'decimal:2',
    ];

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function partyType()
    {
        return $this->belongsTo(PartyType::class);
    }

    public function linkedLedger()
    {
        return $this->belongsTo(ChartOfAccount::class, 'linked_ledger_account_id');
    }
}
