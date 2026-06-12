<?php

namespace App\Models;

use App\Support\PartyAccountingProfile;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Schema;

class Party extends Model
{
    use SoftDeletes;


    protected static function booted(): void
    {
        static::saved(function (Party $party): void {
            if (! Schema::hasTable('party_ledger_mappings') || ! $party->linked_ledger_account_id) {
                return;
            }

            $party->loadMissing('partyType', 'linkedLedger.accountType', 'ledgerMappings');

            $purpose = PartyAccountingProfile::purposeFromNature(
                PartyAccountingProfile::effectiveNature($party)
            );

            if ($purpose === PartyLedgerMapping::PURPOSE_GENERAL) {
                $purpose = PartyAccountingProfile::purposeForAccount($party->linkedLedger);
            }

            $party->ledgerMappings()->updateOrCreate(
                ['mapping_purpose' => $purpose],
                [
                    'company_id' => $party->company_id,
                    'chart_of_account_id' => $party->linked_ledger_account_id,
                    'status' => $party->status === 'Active' ? 'Active' : 'Inactive',
                    'created_by' => $party->created_by,
                    'updated_by' => $party->updated_by,
                ]
            );
        });
    }

    protected $fillable = [
        'company_id',
        'party_code',
        'party_name',
        'party_type_id',
        'sub_type',
        'mobile',
        'email',
        'address',
        'credit_limit',
        'payment_terms',
        'department',
        'designation',
        'salary_amount',
        'ownership_percentage',
        'contact_info',
        'linked_ledger_account_id',
        'default_ledger_nature',
        'opening_balance',
        'opening_balance_type',
        'notes',
        'status',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'opening_balance' => 'decimal:2',
        'credit_limit' => 'decimal:2',
        'salary_amount' => 'decimal:2',
        'ownership_percentage' => 'decimal:2',
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
        return $this->belongsTo(ChartOfAccount::class, 'linked_ledger_account_id')->withTrashed();
    }

    public function ledgerMappings()
    {
        return $this->hasMany(PartyLedgerMapping::class);
    }

    public function activeLedgerMappings()
    {
        return $this->hasMany(PartyLedgerMapping::class)->where('status', 'Active');
    }

    public function receivableLedgerMapping()
    {
        return $this->hasOne(PartyLedgerMapping::class)
            ->where('mapping_purpose', PartyLedgerMapping::PURPOSE_RECEIVABLE);
    }

    public function payableLedgerMapping()
    {
        return $this->hasOne(PartyLedgerMapping::class)
            ->where('mapping_purpose', PartyLedgerMapping::PURPOSE_PAYABLE);
    }

    public function capitalLedgerMapping()
    {
        return $this->hasOne(PartyLedgerMapping::class)
            ->where('mapping_purpose', PartyLedgerMapping::PURPOSE_CAPITAL);
    }


    public function effectiveLedgerNature(): string
    {
        return PartyAccountingProfile::effectiveNature($this);
    }

    public function mappingPurposeForAccount(int $accountId): ?string
    {
        $this->loadMissing('ledgerMappings');

        return $this->ledgerMappings
            ->first(fn (PartyLedgerMapping $mapping) => $mapping->status === 'Active'
                && (int) $mapping->chart_of_account_id === $accountId)
            ?->mapping_purpose;
    }

    public function ledgerFor(string $purpose): ?ChartOfAccount
    {
        $this->loadMissing('ledgerMappings.ledger');

        return $this->ledgerMappings
            ->first(fn (PartyLedgerMapping $mapping) => $mapping->status === 'Active'
                && $mapping->mapping_purpose === $purpose)
            ?->ledger;
    }
    public function openingBalances()
    {
        return $this->hasMany(OpeningBalance::class);
    }
    public function vouchers()
    {
        return $this->hasMany(VoucherHeader::class);
    }
}

