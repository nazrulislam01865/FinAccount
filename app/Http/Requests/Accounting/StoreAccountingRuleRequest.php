<?php

namespace App\Http\Requests\Accounting;

use App\Http\Requests\Accounting\Concerns\ValidatesAccountingOptions;
use App\Models\AccountingOption;
use App\Models\AccountingRule;
use App\Models\AccountingRuleLine;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAccountingRuleRequest extends FormRequest
{
    use ValidatesAccountingOptions;

    public function authorize(): bool
    {
        return $this->user()?->canAccounting('accounting_rules.manage') ?? false;
    }

    public function rules(): array
    {
        $companyId = $this->user()->company_id;

        return [
            'code' => ['required', 'string', 'max:50', Rule::unique('accounting_rules')->where('company_id', $companyId)],
            'name' => ['required', 'string', 'max:255'],
            'category' => ['required', $this->activeAccountingOption(AccountingOption::GROUP_TRANSACTION_CATEGORY)],
            'party_type' => ['required', $this->activeAccountingOption(AccountingOption::GROUP_RULE_PARTY_TYPE)],
            'debit_source' => ['required', $this->activeAccountingOption(AccountingOption::GROUP_ACCOUNTING_SOURCE)],
            'credit_source' => ['required', $this->activeAccountingOption(AccountingOption::GROUP_ACCOUNTING_SOURCE), 'different:debit_source'],
            'supports_split_transaction' => ['required', 'boolean'],
            'money_required' => ['required', 'boolean'],
            'generates_invoice' => ['required', 'boolean'],
            'invoice_title' => ['nullable', 'string', 'max:120'],
            'party_required' => ['required', 'boolean'],
            'is_active' => ['required', 'boolean'],
            'lines' => ['required', 'array', 'min:2', 'max:4'],
            'lines.*.line_side' => ['required', Rule::in([AccountingRuleLine::SIDE_DEBIT, AccountingRuleLine::SIDE_CREDIT])],
            'lines.*.account_source' => ['required', $this->activeAccountingOption(AccountingOption::GROUP_ACCOUNTING_SOURCE)],
            'lines.*.amount_basis' => ['required', Rule::in([AccountingRuleLine::BASIS_TOTAL, AccountingRuleLine::BASIS_PAID, AccountingRuleLine::BASIS_DUE])],
        ];
    }

    protected function prepareForValidation(): void
    {
        $debitSource = (string) $this->input('debit_source');
        $creditSource = (string) $this->input('credit_source');
        $supportsSplit = $this->boolean('supports_split_transaction');

        $this->merge([
            'code' => trim((string) $this->input('code')),
            'name' => trim((string) $this->input('name')),
            'debit_source' => $debitSource,
            'credit_source' => $creditSource,
            'supports_split_transaction' => $supportsSplit,
            'money_required' => $this->boolean('money_required') || $supportsSplit,
            'generates_invoice' => $this->boolean('generates_invoice'),
            'invoice_title' => filled($this->input('invoice_title')) ? trim((string) $this->input('invoice_title')) : null,
            'party_required' => $this->boolean('party_required') || $supportsSplit,
            'is_active' => $this->boolean('is_active'),
            'lines' => $this->linesFromSimpleSetup($debitSource, $creditSource, $supportsSplit),
        ]);
    }

    /** @return array<int, array{line_side: string, account_source: string, amount_basis: string}> */
    private function linesFromSimpleSetup(string $debitSource, string $creditSource, bool $supportsSplit): array
    {
        if (! $supportsSplit) {
            return [
                [
                    'line_side' => AccountingRuleLine::SIDE_DEBIT,
                    'account_source' => $debitSource,
                    'amount_basis' => AccountingRuleLine::BASIS_TOTAL,
                ],
                [
                    'line_side' => AccountingRuleLine::SIDE_CREDIT,
                    'account_source' => $creditSource,
                    'amount_basis' => AccountingRuleLine::BASIS_TOTAL,
                ],
            ];
        }

        if (($this->input('category') ?? '') === 'Sales') {
            return [
                [
                    'line_side' => AccountingRuleLine::SIDE_DEBIT,
                    'account_source' => AccountingRule::SOURCE_SELECTED_MONEY,
                    'amount_basis' => AccountingRuleLine::BASIS_PAID,
                ],
                [
                    'line_side' => AccountingRuleLine::SIDE_DEBIT,
                    'account_source' => AccountingRule::SOURCE_PARTY_RECEIVABLE,
                    'amount_basis' => AccountingRuleLine::BASIS_DUE,
                ],
                [
                    'line_side' => AccountingRuleLine::SIDE_CREDIT,
                    'account_source' => $creditSource ?: AccountingRule::SOURCE_HEAD_ACCOUNT,
                    'amount_basis' => AccountingRuleLine::BASIS_TOTAL,
                ],
            ];
        }

        return [
            [
                'line_side' => AccountingRuleLine::SIDE_DEBIT,
                'account_source' => $debitSource ?: AccountingRule::SOURCE_HEAD_ACCOUNT,
                'amount_basis' => AccountingRuleLine::BASIS_TOTAL,
            ],
            [
                'line_side' => AccountingRuleLine::SIDE_CREDIT,
                'account_source' => AccountingRule::SOURCE_SELECTED_MONEY,
                'amount_basis' => AccountingRuleLine::BASIS_PAID,
            ],
            [
                'line_side' => AccountingRuleLine::SIDE_CREDIT,
                'account_source' => AccountingRule::SOURCE_PARTY_PAYABLE,
                'amount_basis' => AccountingRuleLine::BASIS_DUE,
            ],
        ];
    }
}
