<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class VoucherNumberingRule extends Model
{
    use SoftDeletes;

    /*
     * These are only default suggestions.
     * Users can create new voucher types as needed.
     */
    public const VOUCHER_TYPES = [
        'Payment Voucher' => 'Cash/bank payments',
        'Receipt Voucher' => 'Cash/bank receipts',
        'Journal Voucher' => 'Due, adjustment, opening balance',
        'Contra / Transfer Voucher' => 'Cash to bank or bank to bank transfer',
        'Draft Voucher' => 'Unposted draft transactions',
    ];

    public const DEFAULT_PREFIXES = [
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
        return max($this->starting_number, $this->last_number + 1);
    }

    public function getFirstVoucherNumberAttribute(): string
    {
        return $this->generate($this->starting_number);
    }

    public function getNextVoucherNumberAttribute(): string
    {
        return $this->generate($this->next_number);
    }

    public function generate(?int $number = null, ?CarbonInterface $voucherDate = null): string
    {
        $number ??= $this->next_number;
        $voucherDate ??= now();

        $output = str_replace(
            ['{YYYY}', '{YY}', '{MM}'],
            [
                $voucherDate->format('Y'),
                $voucherDate->format('y'),
                $voucherDate->format('m'),
            ],
            $this->format_template
        );

        return preg_replace_callback('/\{0+\}/', function ($matches) use ($number) {
            $length = strlen($matches[0]) - 2;

            return str_pad((string) $number, $length, '0', STR_PAD_LEFT);
        }, $output);
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'Active');
    }
}
