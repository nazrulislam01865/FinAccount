<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Company extends Model
{
    use HasFactory;

    protected $fillable = [
        'code', 'name', 'short_name', 'business_type_id',
        'trade_license_no', 'bin_vat_registration_no', 'tin',
        'currency_code', 'currency_id', 'accounting_method',
        'timezone', 'time_zone_id', 'default_financial_year_id', 'default_branch',
        'address', 'contact_email', 'contact_phone', 'website',
        'logo_path', 'favicon_path', 'setup_completed_at', 'status', 'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'business_type_id' => 'integer',
            'currency_id' => 'integer',
            'time_zone_id' => 'integer',
            'default_financial_year_id' => 'integer',
            'setup_completed_at' => 'datetime',
            'updated_by' => 'integer',
        ];
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function businessType(): BelongsTo
    {
        return $this->belongsTo(BusinessType::class);
    }

    public function currency(): BelongsTo
    {
        return $this->belongsTo(Currency::class);
    }

    public function timeZone(): BelongsTo
    {
        return $this->belongsTo(TimeZone::class);
    }

    public function defaultFinancialYear(): BelongsTo
    {
        return $this->belongsTo(FinancialYear::class, 'default_financial_year_id');
    }

    public function financialYears(): HasMany
    {
        return $this->hasMany(FinancialYear::class);
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function isActiveForPosting(): bool
    {
        return strtolower((string) $this->status) === 'active';
    }

    public function isSetupComplete(): bool
    {
        return $this->setup_completed_at !== null
            && $this->business_type_id !== null
            && $this->currency_id !== null
            && $this->time_zone_id !== null
            && $this->default_financial_year_id !== null;
    }
}
