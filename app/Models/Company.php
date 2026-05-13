<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Company extends Model
{
    protected $fillable = [
        'company_name',
        'short_name',
        'business_type_id',
        'trade_license_no',
        'tax_id_bin',
        'currency_id',
        'time_zone_id',
        'financial_year_start',
        'financial_year_end',
        'default_branch',
        'address',
        'contact_email',
        'contact_phone',
        'website',
        'logo_path',
        'journal_voucher_prefix',
        'payment_voucher_prefix',
        'receipt_voucher_prefix',
        'enable_multi_branch',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'financial_year_start' => 'date',
        'financial_year_end' => 'date',
        'enable_multi_branch' => 'boolean',
    ];

    public function businessType()
    {
        return $this->belongsTo(BusinessType::class);
    }

    public function currency()
    {
        return $this->belongsTo(Currency::class);
    }

    public function timeZone()
    {
        return $this->belongsTo(TimeZone::class);
    }
}
