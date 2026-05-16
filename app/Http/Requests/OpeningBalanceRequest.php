<?php

namespace App\Http\Requests;

use App\Models\ChartOfAccount;
use App\Models\FinancialYear;
use App\Models\OpeningBalance;
use App\Models\Party;
use Carbon\Carbon;
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
        $items = collect($this->input('items', []))
            ->map(function ($item) {
                return [
                    'account_id' => $item['account_id'] ?? null,
                    'party_id' => $item['party_id'] ?? null,
                    'debit_opening' => $this->amount($item['debit_opening'] ?? 0),
                    'credit_opening' => $this->amount($item['credit_opening'] ?? 0),
                    'remarks' => $this->blankToNull($item['remarks'] ?? null),
                ];
            })
            ->values()
            ->all();

        $this->merge([
            'financial_year_id' => $this->financial_year_id ?: null,
            'balance_date' => $this->balance_date ?: null,
            'branch_location' => $this->blankToNull($this->branch_location),
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

            'balance_date' => ['required', 'date'],
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
                        ->where('account_level', 'Ledger')
                        ->where('posting_allowed', true)
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

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $financialYear = FinancialYear::query()->find($this->integer('financial_year_id'));

            if (!$financialYear) {
                return;
            }

            $balanceDate = Carbon::parse($this->input('balance_date'));

            if ($balanceDate->lt($financialYear->start_date) || $balanceDate->gt($financialYear->end_date)) {
                $validator->errors()->add(
                    'balance_date',
                    'Opening Balance Date must be inside the selected Financial Year.'
                );
            }

            if ($this->alreadyFinalized()) {
                $validator->errors()->add(
                    'status',
                    'Opening balance for this Financial Year and Branch/Location is already finalized. Posted opening balances cannot be edited directly.'
                );

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
                ->with(['partyType', 'linkedLedger.accountType'])
                ->whereIn('id', $partyIds)
                ->get()
                ->keyBy('id');

            $partyCountsByLedger = Party::query()
                ->where('status', 'Active')
                ->whereIn('linked_ledger_account_id', $accountIds)
                ->selectRaw('linked_ledger_account_id, COUNT(*) as total')
                ->groupBy('linked_ledger_account_id')
                ->pluck('total', 'linked_ledger_account_id');

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

                if (!$account) {
                    continue;
                }

                if ($account->account_level !== 'Ledger' || !$account->posting_allowed) {
                    $validator->errors()->add(
                        "items.$index.account_id",
                        'Opening balance can be entered only for active posting ledger accounts.'
                    );
                }

                $accountType = $account->accountType?->name;
                $normalBalance = $account->normal_balance ?: $account->accountType?->normal_balance;

                if ($normalBalance === 'Debit' && $credit > 0) {
                    $validator->errors()->add(
                        "items.$index.credit_opening",
                        'Asset and Expense accounts normally carry opening debit balance. Enter the amount in Debit Opening.'
                    );
                }

                if ($normalBalance === 'Credit' && $debit > 0) {
                    $validator->errors()->add(
                        "items.$index.debit_opening",
                        'Liability, Equity, and Income accounts normally carry opening credit balance. Enter the amount in Credit Opening.'
                    );
                }

                if ($this->accountRequiresParty($account, (int) ($partyCountsByLedger[$accountId] ?? 0)) && !$partyId) {
                    $validator->errors()->add(
                        "items.$index.party_id",
                        'Party / Sub-ledger is required for receivable, payable, and advance opening balances.'
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

                    if ($party) {
                        $this->validatePartyBalanceSide($validator, $index, $party, $accountType, $normalBalance, $debit, $credit);
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
                    "Opening balance total debit must equal total credit before posting. Difference: {$difference}."
                );
            }
        });
    }

    public function messages(): array
    {
        return [
            'financial_year_id.required' => 'Financial Year is required.',
            'financial_year_id.exists' => 'Selected Financial Year is invalid or inactive.',

            'balance_date.required' => 'Opening Balance Date is required.',
            'balance_date.date' => 'Opening Balance Date must be a valid date.',

            'items.required' => 'Opening balance rows are required.',
            'items.min' => 'At least one opening balance row is required.',

            'items.*.account_id.required' => 'Account is required for every opening balance row.',
            'items.*.account_id.exists' => 'Selected account must be an active posting ledger account.',

            'items.*.party_id.exists' => 'Selected party is invalid or inactive.',

            'items.*.debit_opening.numeric' => 'Debit opening must be a valid number.',
            'items.*.credit_opening.numeric' => 'Credit opening must be a valid number.',

            'items.*.debit_opening.min' => 'Debit opening cannot be negative.',
            'items.*.credit_opening.min' => 'Credit opening cannot be negative.',

            'status.in' => 'Opening balance status must be Draft or Final.',
        ];
    }

    private function alreadyFinalized(): bool
    {
        $branchLocation = $this->blankToNull($this->input('branch_location'));

        return OpeningBalance::query()
            ->where('financial_year_id', $this->integer('financial_year_id'))
            ->where('status', 'Final')
            ->where(function ($query) use ($branchLocation) {
                if ($branchLocation === null) {
                    $query->whereNull('branch_location');
                } else {
                    $query->where('branch_location', $branchLocation);
                }
            })
            ->exists();
    }

    private function accountRequiresParty(ChartOfAccount $account, int $linkedPartyCount): bool
    {
        if ($linkedPartyCount > 0) {
            return true;
        }

        $name = strtoupper($account->account_name);

        return str_contains($name, 'RECEIVABLE')
            || str_contains($name, 'PAYABLE')
            || str_contains($name, 'ADVANCE TO')
            || str_contains($name, 'ADVANCE FROM')
            || str_contains($name, 'CUSTOMER DUE')
            || str_contains($name, 'SUPPLIER DUE');
    }

    private function validatePartyBalanceSide(
        Validator $validator,
        int $index,
        Party $party,
        ?string $accountType,
        ?string $normalBalance,
        float $debit,
        float $credit
    ): void {
        $nature = $party->default_ledger_nature ?: $this->inferPartyNature($party, $accountType, $normalBalance);

        if (in_array($nature, ['Receivable', 'Advance Paid'], true) && $credit > 0) {
            $validator->errors()->add(
                "items.$index.credit_opening",
                'Receivable and advance paid party balances must be entered as Debit Opening.'
            );
        }

        if (in_array($nature, ['Payable', 'Advance Received'], true) && $debit > 0) {
            $validator->errors()->add(
                "items.$index.debit_opening",
                'Payable and advance received party balances must be entered as Credit Opening.'
            );
        }
    }

    private function inferPartyNature(Party $party, ?string $accountType, ?string $normalBalance): string
    {
        $value = strtoupper(trim(($party->partyType?->code ?? '') . ' ' . ($party->partyType?->name ?? '') . ' ' . ($party->sub_type ?? '')));

        if (str_contains($value, 'CUSTOMER') || str_contains($value, 'TENANT')) {
            return 'Receivable';
        }

        if (str_contains($value, 'SUPPLIER') || str_contains($value, 'VENDOR') || str_contains($value, 'EMPLOYEE') || str_contains($value, 'DRIVER') || str_contains($value, 'LANDLORD')) {
            return 'Payable';
        }

        if ($accountType === 'Asset' && $normalBalance === 'Debit') {
            return 'Receivable';
        }

        if ($accountType === 'Liability' && $normalBalance === 'Credit') {
            return 'Payable';
        }

        return 'No Effect';
    }

    private function blankToNull(mixed $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    private function amount(mixed $value): float
    {
        return round((float) str_replace(',', '', (string) $value), 2);
    }
}