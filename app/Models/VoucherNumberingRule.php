<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class VoucherNumberingRule extends Model
{
    use SoftDeletes;

    public const VOUCHER_TYPES = [
        'Opening Voucher',
        'Payment Voucher',
        'Receipt Voucher',
        'Journal Voucher',
        'Contra / Transfer Voucher',
        'Draft Voucher',
    ];

    public const DEFAULT_PREFIXES = [
        'Opening Voucher' => 'OP',
        'Payment Voucher' => 'PV',
        'Receipt Voucher' => 'RV',
        'Journal Voucher' => 'JV',
        'Contra / Transfer Voucher' => 'CV',
        'Draft Voucher' => 'DR',
    ];

    protected $fillable = [
        'company_id',
        'financial_year_id',
        'voucher_type',
        'prefix',
        'format_template',
        'starting_number',
        'number_length',
        'last_number',
        'reset_every_year',
        'used_for',
        'status',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'starting_number' => 'integer',
        'number_length' => 'integer',
        'last_number' => 'integer',
        'reset_every_year' => 'boolean',
    ];

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function financialYear()
    {
        return $this->belongsTo(FinancialYear::class);
    }

    public function getNextNumberAttribute(): int
    {
        return max((int) $this->starting_number, (int) $this->last_number + 1);
    }

    public function generate(int $number, ?CarbonInterface $voucherDate = null): string
    {
        $date = $voucherDate ?? now();

        $paddedNumber = str_pad(
            (string) $number,
            (int) $this->number_length,
            '0',
            STR_PAD_LEFT
        );

        return str_replace(
            [
                '{YYYY}',
                '{YY}',
                '{00000}',
                '{NUMBER}',
            ],
            [
                $date->format('Y'),
                $date->format('y'),
                $paddedNumber,
                $paddedNumber,
            ],
            $this->format_template
        );
    }
}