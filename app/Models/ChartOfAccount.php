<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ChartOfAccount extends Model
{
    protected $fillable = [
        'company_id',
        'parent_id',
        'level',
        'code',
        'name',
        'type',
        'normal_balance',
        'report_section',
        'cash_flow_section',
        'is_cash_bank',
        'is_party_control',
        'is_posting',
        'sort_order',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'level' => 'integer',
            'is_cash_bank' => 'boolean',
            'is_party_control' => 'boolean',
            'is_posting' => 'boolean',
            'sort_order' => 'integer',
            'is_active' => 'boolean',
        ];
    }


    protected static function booted(): void
    {
        static::saving(function (ChartOfAccount $account): void {
            $setup = self::automaticReportSetup(
                (string) $account->type,
                (string) $account->name,
                $account->parent_id ? self::query()->find((int) $account->parent_id) : null,
                (int) ($account->level ?: 3),
            );

            $account->report_section = $setup['report_section'];
            $account->cash_flow_section = $setup['cash_flow_section'];
            $account->is_cash_bank = $setup['is_cash_bank'];
            $account->is_party_control = $setup['is_party_control'];
            $account->is_posting = $setup['is_posting'];
            $account->sort_order = $setup['sort_order'];
        });
    }

    /** @return array{report_section: string, cash_flow_section: ?string, is_cash_bank: bool, is_party_control: bool, is_posting: bool, sort_order: int} */
    private static function automaticReportSetup(string $type, string $name, ?ChartOfAccount $parent, int $level): array
    {
        $reportSection = self::guessReportSection($type, $name, $parent);

        return [
            'report_section' => $reportSection,
            'cash_flow_section' => self::guessCashFlowSection($type, $name, $reportSection),
            'is_cash_bank' => self::looksLikeCashBankAccount($type, $name),
            'is_party_control' => self::looksLikePartyControlAccount($type, $name, $reportSection),
            'is_posting' => $level >= 3,
            'sort_order' => self::reportSortOrder($type, $reportSection),
        ];
    }

    private static function guessReportSection(string $type, string $name, ?ChartOfAccount $parent = null): string
    {
        $text = self::normaliseText($name);

        if ($parent && (int) $parent->level >= 2 && filled($parent->report_section)) {
            return (string) $parent->report_section;
        }

        return match ($type) {
            'Income' => self::matchesAny($text, [
                'interest', 'discount received', 'gain', 'commission received', 'other income', 'misc income', 'non operating',
            ]) ? 'Other Income' : 'Revenue',

            'Expense' => match (true) {
                self::matchesAny($text, [
                    'purchase', 'cost of sale', 'cost of sales', 'cogs', 'product cost', 'service cost', 'direct cost',
                    'production', 'raw material', 'factory', 'manufacturing', 'inventory cost',
                ]) => 'Cost of Sales',
                self::matchesAny($text, [
                    'bank charge', 'bank fee', 'finance cost', 'interest', 'loan', 'processing fee', 'card charge',
                ]) => 'Financial Expense',
                self::matchesAny($text, ['tax', 'vat', 'ait', 'income tax']) => 'Tax Expense',
                self::matchesAny($text, [
                    'advertisement', 'advertising', 'marketing', 'delivery', 'sales commission', 'promotion', 'courier',
                ]) => 'Selling Expense',
                self::matchesAny($text, ['office', 'admin', 'stationery', 'audit', 'legal', 'professional']) => 'Administrative Expense',
                default => 'Operating Expense',
            },

            'Asset' => match (true) {
                self::matchesAny($text, [
                    'fixed', 'equipment', 'furniture', 'vehicle', 'building', 'land', 'machinery', 'computer',
                    'depreciation', 'non current', 'non-current',
                ]) => 'Fixed Asset',
                default => 'Current Asset',
            },

            'Liability' => self::matchesAny($text, ['long term', 'long-term', 'non current', 'non-current'])
                ? 'Non Current Liability'
                : 'Current Liability',

            'Equity' => match (true) {
                self::matchesAny($text, ['capital', 'owner']) => 'Owner Capital',
                self::matchesAny($text, ['retained']) => 'Retained Earnings',
                default => 'Equity',
            },

            default => 'General',
        };
    }

    private static function guessCashFlowSection(string $type, string $name, string $reportSection): ?string
    {
        if (self::looksLikeCashBankAccount($type, $name)) {
            return 'Cash Bank';
        }

        return match ($type) {
            'Income', 'Expense' => 'Operating',
            'Asset' => $reportSection === 'Current Asset' ? 'Operating' : 'Investing',
            'Liability', 'Equity' => 'Financing',
            default => null,
        };
    }

    private static function looksLikeCashBankAccount(string $type, string $name): bool
    {
        return $type === 'Asset' && self::matchesAny(self::normaliseText($name), [
            'cash', 'bank', 'petty cash', 'bkash', 'b-kash', 'nagad', 'rocket', 'wallet', 'mobile banking', 'card',
        ]);
    }

    private static function looksLikePartyControlAccount(string $type, string $name, string $reportSection): bool
    {
        $text = self::normaliseText($name);

        if (in_array($type, ['Asset', 'Liability'], true) && self::matchesAny($text, [
            'receivable', 'payable', 'customer', 'supplier', 'vendor', 'party', 'due', 'advance',
        ])) {
            return true;
        }

        return in_array($reportSection, ['Current Asset', 'Current Liability'], true)
            && self::matchesAny($text, ['receivable', 'payable']);
    }

    private static function reportSortOrder(string $type, string $reportSection): int
    {
        return [
            'Current Asset' => 100,
            'Fixed Asset' => 120,
            'Non Current Asset' => 130,
            'Current Liability' => 200,
            'Non Current Liability' => 220,
            'Equity' => 300,
            'Owner Capital' => 310,
            'Retained Earnings' => 320,
            'Revenue' => 400,
            'Cost of Sales' => 500,
            'Operating Expense' => 600,
            'Administrative Expense' => 610,
            'Selling Expense' => 620,
            'Financial Expense' => 700,
            'Other Income' => 800,
            'Tax Expense' => 900,
        ][$reportSection] ?? match ($type) {
            'Asset' => 100,
            'Liability' => 200,
            'Equity' => 300,
            'Income' => 400,
            'Expense' => 600,
            default => 999,
        };
    }

    /** @param array<int, string> $needles */
    private static function matchesAny(string $text, array $needles): bool
    {
        foreach ($needles as $needle) {
            if ($needle !== '' && str_contains($text, $needle)) {
                return true;
            }
        }

        return false;
    }

    private static function normaliseText(string $value): string
    {
        return str_replace(['_', '-', '/', '.', ','], ' ', mb_strtolower(trim($value)));
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('code');
    }

    public function scopePostingAccounts(Builder $query): Builder
    {
        return $query->where('level', 3)->where('is_posting', true);
    }

    public function journalLines(): HasMany
    {
        return $this->hasMany(JournalLine::class);
    }

    public function moneyAccounts(): HasMany
    {
        return $this->hasMany(MoneyAccount::class);
    }

    public function receivableParties(): HasMany
    {
        return $this->hasMany(Party::class, 'receivable_account_id');
    }

    public function payableParties(): HasMany
    {
        return $this->hasMany(Party::class, 'payable_account_id');
    }

    public function transactionHeads(): HasMany
    {
        return $this->hasMany(TransactionHead::class, 'posting_account_id');
    }
}
