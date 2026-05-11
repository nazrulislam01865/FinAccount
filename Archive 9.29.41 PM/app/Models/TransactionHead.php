<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class TransactionHead extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'company_id', 'name', 'nature', 'default_party_type_id', 'requires_party',
        'requires_reference', 'description', 'status', 'created_by', 'updated_by'
    ];

    protected $casts = [
        'requires_party' => 'boolean',
        'requires_reference' => 'boolean',
    ];

    public function company() { return $this->belongsTo(Company::class); }
    public function defaultPartyType() { return $this->belongsTo(PartyType::class, 'default_party_type_id'); }
    public function settlementTypes() { return $this->belongsToMany(SettlementType::class)->withTimestamps(); }
}
