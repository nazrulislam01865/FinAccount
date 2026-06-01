<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class TransactionHead extends Model
{
    use SoftDeletes;

    public const TRANSACTION_CATEGORIES = [
        'Sales',
        'Purchase',
        'Receipt',
        'Payment',
        'Banking',
        'Expense',
        'Income',
        'Owner / Equity',
        'Asset',
        'Loan',
        'Employee',
        'Opening',
        'Adjustment',
    ];

    protected $fillable = [
        'company_id',
        'head_code',
        'name',
        'nature',
        'category',
        'default_party_type_id',
        'default_primary_ledger_id',
        'default_movement',
        'payment_method_required',
        'party_required_mode',
        'transaction_screen',
        'is_system_default',
        'is_user_selectable',
        'sort_order',
        'linked_accounting_rule_code',
        'requires_party',
        'requires_reference',
        'description',
        'help_text',
        'developer_note',
        'status',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'requires_party' => 'boolean',
        'requires_reference' => 'boolean',
        'payment_method_required' => 'boolean',
        'is_system_default' => 'boolean',
        'is_user_selectable' => 'boolean',
        'sort_order' => 'integer',
    ];

    public static function transactionCategories(): array
    {
        return self::TRANSACTION_CATEGORIES;
    }

    public static function normaliseCategory(?string $category, ?string $name = null, ?string $nature = null): string
    {
        $raw = trim((string) ($category ?: $nature ?: ''));
        $haystack = strtolower(trim($raw . ' ' . (string) $name . ' ' . (string) $nature));

        if ($raw === 'Owner / Equity') {
            return 'Owner / Equity';
        }

        foreach (self::TRANSACTION_CATEGORIES as $allowedCategory) {
            if (strcasecmp($raw, $allowedCategory) === 0) {
                return $allowedCategory;
            }
        }

        if (str_contains($haystack, 'opening')) {
            return 'Opening';
        }

        if (str_contains($haystack, 'employee') || str_contains($haystack, 'salary')) {
            return 'Employee';
        }

        if (str_contains($haystack, 'loan')) {
            return 'Loan';
        }

        if (str_contains($haystack, 'owner') || str_contains($haystack, 'equity') || str_contains($haystack, 'capital') || str_contains($haystack, 'withdrawal') || str_contains($haystack, 'drawing')) {
            return 'Owner / Equity';
        }

        if (str_contains($haystack, 'asset')) {
            return 'Asset';
        }

        if (str_contains($haystack, 'bank') || str_contains($haystack, 'transfer') || str_contains($haystack, 'charge') || str_contains($haystack, 'interest')) {
            return 'Banking';
        }

        if (str_contains($haystack, 'income') || str_contains($haystack, 'service')) {
            return 'Income';
        }

        if (str_contains($haystack, 'expense') || str_contains($haystack, 'rent') || str_contains($haystack, 'utility') || str_contains($haystack, 'office')) {
            return 'Expense';
        }

        if (str_contains($haystack, 'purchase') || str_contains($haystack, 'supplier due') || str_contains($haystack, 'payable')) {
            return 'Purchase';
        }

        if (str_contains($haystack, 'sales') || str_contains($haystack, 'sale') || str_contains($haystack, 'customer due') || str_contains($haystack, 'receivable')) {
            return 'Sales';
        }

        if (str_contains($haystack, 'receipt') || str_contains($haystack, 'collection') || str_contains($haystack, 'received')) {
            return 'Receipt';
        }

        if (str_contains($haystack, 'payment') || str_contains($haystack, 'paid')) {
            return 'Payment';
        }

        if (str_contains($haystack, 'adjust') || str_contains($haystack, 'journal') || str_contains($haystack, 'other')) {
            return 'Adjustment';
        }

        return 'Payment';
    }

    public static function natureFromCategory(?string $category): string
    {
        return match (self::normaliseCategory($category)) {
            'Sales', 'Receipt', 'Income' => 'Receipt',
            'Purchase' => 'Purchase',
            'Expense', 'Payment', 'Employee' => 'Payment',
            'Banking' => 'Adjustment',
            'Owner / Equity' => 'Equity',
            'Asset' => 'Asset',
            'Loan' => 'Loan',
            'Opening', 'Adjustment' => 'Adjustment',
            default => 'Payment',
        };
    }

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function defaultPartyType()
    {
        return $this->belongsTo(PartyType::class, 'default_party_type_id');
    }

    public function defaultPrimaryLedger()
    {
        return $this->belongsTo(ChartOfAccount::class, 'default_primary_ledger_id');
    }

    public function settlementTypes()
    {
        return $this->belongsToMany(
            SettlementType::class,
            'settlement_type_transaction_head',
            'transaction_head_id',
            'settlement_type_id'
        )->withTimestamps();
    }

    public function ledgerMappingRules()
    {
        return $this->hasMany(LedgerMappingRule::class);
    }

    public function vouchers()
    {
        return $this->hasMany(VoucherHeader::class);
    }
}
