<?php

namespace App\Http\Requests;

use App\Models\ChartOfAccount;
use App\Models\Party;
use App\Services\Accounting\FinancialYearService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class OpeningBalanceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $currentFinancialYear = app(FinancialYearService::class)
            ->current($this->user()?->id);

        $items = collect($this->input('items', []))
            ->map(function ($item) {
                return [
                    'account_id' => $item['account_id'] ?? null,
                    'party_id' => $item['party_id'] ?? null,
                    'debit_opening' => $this->amount($item['debit_opening'] ?? 0),
                    'credit_opening' => $this->amount($item['credit_opening'] ?? 0),
                    'remarks' => $item['remarks'] ?? null,
                ];
            })
            ->values()
            ->all();

        $this->merge([
            'financial_year_id' => $currentFinancialYear?->id,
            'branch_location' => $this->branch_location ?: null,
            'status' => $this->status ?: 'Draft',
            'items' => $items,
        ]);
    }

    public function rules(): array
    {
        return [
            'financial_year_id' => [
                'required',
                'integer',
                Rule::exists('financial_years', 'id')
                    ->where(fn ($query) => $query
                        ->where('status', 'Active')
                        ->whereNull('deleted_at')),
            ],

            'branch_location' => ['nullable', 'string', 'max:150'],

            'status' => [
                'required',
                Rule::in(['Draft', 'Final']),
            ],

            'items' => ['required', 'array', 'min:1'],

            'items.*.account_id' => [
                'required',
                'integer',
                Rule::exists('chart_of_accounts', 'id')
                    ->where(fn ($query) => $query
                        ->where('status', 'Active')
                        ->whereNull('deleted_at')),
            ],

            'items.*.party_id' => [
                'nullable',
                'integer',
                Rule::exists('parties', 'id')
                    ->where(fn ($query) => $query
                        ->where('status', 'Active')
                        ->whereNull('deleted_at')),
            ],

            'items.*.debit_opening' => ['nullable', 'numeric', 'min:0'],
            'items.*.credit_opening' => ['nullable', 'numeric', 'min:0'],
            'items.*.remarks' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $items = collect($this->input('items', []));

            $nonZeroItems = $items->filter(function ($item) {
                return $this->amount($item['debit_opening'] ?? 0) > 0
                    || $this->amount($item['credit_opening'] ?? 0) > 0;
            });

            if ($nonZeroItems->isEmpty()) {
                $validator->errors()->add(
                    'items',
                    'At least one opening balance row must have a debit or credit amount.'
                );

                return;
            }

            $accountIds = $items->pluck('account_id')->filter()->unique()->values();
            $partyIds = $items->pluck('party_id')->filter()->unique()->values();

            $accounts = ChartOfAccount::query()
                ->with('accountType')
                ->whereIn('id', $accountIds)
                ->get()
                ->keyBy('id');

            $parties = Party::query()
                ->whereIn('id', $partyIds)
                ->get()
                ->keyBy('id');

            $seenRows = [];
            $totalDebit = 0.0;
            $totalCredit = 0.0;

            foreach ($items as $index => $item) {
                $accountId = (int) ($item['account_id'] ?? 0);
                $partyId = $item['party_id'] ? (int) $item['party_id'] : null;

                $debit = $this->amount($item['debit_opening'] ?? 0);
                $credit = $this->amount($item['credit_opening'] ?? 0);

                if ($debit <= 0 && $credit <= 0) {
                    continue;
                }

                if ($debit > 0 && $credit > 0) {
                    $validator->errors()->add(
                        "items.$index.debit_opening",
                        'A row cannot have both debit and credit opening amount.'
                    );
                }

                $account = $accounts[$accountId] ?? null;
                $normalBalance = $account?->accountType?->normal_balance;

                if ($normalBalance === 'Debit' && $credit > 0) {
                    $validator->errors()->add(
                        "items.$index.credit_opening",
                        'Asset and Expense accounts must carry opening debit balance.'
                    );
                }

                if ($normalBalance === 'Credit' && $debit > 0) {
                    $validator->errors()->add(
                        "items.$index.debit_opening",
                        'Liability, Equity, and Income accounts must carry opening credit balance.'
                    );
                }

                if ($partyId) {
                    $party = $parties[$partyId] ?? null;

                    if ($party && (int) $party->linked_ledger_account_id !== $accountId) {
                        $validator->errors()->add(
                            "items.$index.party_id",
                            'Selected party is not linked with the selected ledger account.'
                        );
                    }
                }

                $rowKey = $accountId . ':' . ($partyId ?: 'none');

                if (isset($seenRows[$rowKey])) {
                    $validator->errors()->add(
                        "items.$index.account_id",
                        'Duplicate opening balance row found for the same account and party.'
                    );
                }

                $seenRows[$rowKey] = true;

                $totalDebit += $debit;
                $totalCredit += $credit;
            }

            if (round($totalDebit, 2) !== round($totalCredit, 2)) {
                $difference = number_format(abs($totalDebit - $totalCredit), 2);

                $validator->errors()->add(
                    'items',
                    "Total debit opening balance must equal total credit opening balance. Difference: {$difference}."
                );
            }
        }];
    }

    public function messages(): array
    {
        return [
            'financial_year_id.required' => 'Financial Year is required.',
            'financial_year_id.exists' => 'Selected Financial Year is invalid or inactive.',

            'items.required' => 'Opening balance rows are required.',

            'items.*.account_id.required' => 'Account is required for every opening balance row.',
            'items.*.account_id.exists' => 'Selected account is invalid or inactive.',

            'items.*.party_id.exists' => 'Selected party is invalid or inactive.',

            'items.*.debit_opening.numeric' => 'Debit opening must be a valid number.',
            'items.*.credit_opening.numeric' => 'Credit opening must be a valid number.',

            'items.*.debit_opening.min' => 'Debit opening cannot be negative.',
            'items.*.credit_opening.min' => 'Credit opening cannot be negative.',

            'status.in' => 'Opening balance status must be Draft or Final.',
        ];
    }

    private function amount(mixed $value): float
    {
        return round((float) str_replace(',', '', (string) $value), 2);
    }
}
