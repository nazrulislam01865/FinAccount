<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AccountingRuleLine extends Model
{
    public const LEDGER_SOURCES = [
        'fixed',
        'user_cash_bank',
        'party_control',
        'party_receivable',
        'party_payable',
        'party_advance_paid',
        'party_advance_received',
        'party_loan_payable',
        'party_salary_payable',
        'party_capital',
        'transaction_head',
        'system_derived',
    ];

    public const SIDES = [
        'Debit',
        'Credit',
    ];

    protected $fillable = [
        'accounting_rule_id',
        'line_role',
        'ledger_source',
        'ledger_id',
        'side',
        'movement',
        'selection_method',
        'allowed_ledger_type',
        'amount_source',
        'amount_formula',
        'explanation',
        'sort_order',
    ];

    protected $casts = [
        'sort_order' => 'integer',
    ];

    public function accountingRule()
    {
        return $this->belongsTo(AccountingRule::class);
    }

    public function ledger()
    {
        return $this->belongsTo(ChartOfAccount::class, 'ledger_id');
    }

    public function isDebit(): bool
    {
        return $this->side === 'Debit';
    }

    public function isCredit(): bool
    {
        return $this->side === 'Credit';
    }
}
