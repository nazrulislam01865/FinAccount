<?php

namespace App\Http\Requests;

use App\Models\ChartOfAccount;
use App\Models\Party;
use App\Models\PartyLedgerMapping;
use App\Models\PartyType;
use App\Support\PartyAccountingProfile;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;
use Illuminate\Support\Str;

class PartyRequest extends FormRequest
{
    public const LEDGER_NATURES = PartyAccountingProfile::NATURES;

    public function authorize(): bool
    {
        return (bool) $this->user()?->hasAnyPermission('parties.manage');
    }

    protected function prepareForValidation(): void
    {
        /** @var Party|null $party */
        $party = $this->route('party');
        $party?->loadMissing('ledgerMappings.ledger.accountType', 'partyType');

        $companyId = (int) ($this->user()?->company_id ?: $party?->company_id ?: 0);
        $partyType = $this->party_type_id
            ? PartyType::query()->find($this->party_type_id)
            : $party?->partyType;

        // Ledger Nature is never trusted from the Party form. It is derived server-side
        // from Party Type and explicit ledger mappings so users cannot change posting behavior.
        $configuredLedgerNature = PartyAccountingProfile::deriveNature(
            $partyType,
            [],
            $party?->default_ledger_nature
        );

        $mappings = $this->existingAdvancedMappings($party);
        $this->mergeSubmittedMappingArray($mappings);

        $fieldMap = [
            'receivable_ledger_account_id' => PartyLedgerMapping::PURPOSE_RECEIVABLE,
            'payable_ledger_account_id' => PartyLedgerMapping::PURPOSE_PAYABLE,
            'capital_ledger_account_id' => PartyLedgerMapping::PURPOSE_CAPITAL,
        ];

        foreach ($fieldMap as $field => $purpose) {
            if ($this->exists($field)) {
                $ledgerId = $this->nullableInteger($this->input($field));

                if ($ledgerId) {
                    $mappings[$purpose] = $ledgerId;
                } else {
                    unset($mappings[$purpose]);
                }
            }
        }

        if ($this->exists('payable_capital_ledger_account_id')) {
            $ledgerId = $this->nullableInteger($this->input('payable_capital_ledger_account_id'));
            unset($mappings[PartyLedgerMapping::PURPOSE_PAYABLE], $mappings[PartyLedgerMapping::PURPOSE_CAPITAL]);

            if ($ledgerId) {
                $accountType = ChartOfAccount::query()->with('accountType')->find($ledgerId)?->accountType?->name;
                $purpose = $accountType === 'Equity'
                    ? PartyLedgerMapping::PURPOSE_CAPITAL
                    : PartyLedgerMapping::PURPOSE_PAYABLE;
                $mappings[$purpose] = $ledgerId;
            }
        }

        $legacyLinkedLedgerId = $this->nullableInteger($this->input('linked_ledger_account_id'));
        $defaultLedgerId = $this->defaultLedgerForCompany($partyType, $companyId);
        $defaultPurpose = PartyAccountingProfile::purposeFromNature($configuredLedgerNature);

        if ($defaultLedgerId && $defaultPurpose === PartyLedgerMapping::PURPOSE_GENERAL) {
            $defaultLedger = ChartOfAccount::query()->with('accountType')->find($defaultLedgerId);
            $defaultPurpose = PartyAccountingProfile::purposeForAccount($defaultLedger);
        }

        if (! $party && $mappings === [] && $defaultLedgerId) {
            $mappings[$defaultPurpose] = $defaultLedgerId;
        }

        if ($legacyLinkedLedgerId && ! isset($mappings[$defaultPurpose])) {
            $mappings[$defaultPurpose] = $legacyLinkedLedgerId;
        }

        $defaultLedgerNature = PartyAccountingProfile::deriveNature(
            $partyType,
            array_keys($mappings),
            $party?->default_ledger_nature ?: $configuredLedgerNature
        );
        $naturePurpose = PartyAccountingProfile::purposeFromNature($defaultLedgerNature);

        $primaryLedgerId = $mappings[$naturePurpose]
            ?? $legacyLinkedLedgerId
            ?? $mappings[PartyLedgerMapping::PURPOSE_RECEIVABLE]
            ?? $mappings[PartyLedgerMapping::PURPOSE_PAYABLE]
            ?? $mappings[PartyLedgerMapping::PURPOSE_CAPITAL]
            ?? (reset($mappings) ?: null);

        $openingBalance = $this->normalizeMoney($this->opening_balance);
        $primaryLedger = $primaryLedgerId
            ? ChartOfAccount::query()->with('accountType')->find($primaryLedgerId)
            : null;

        $this->merge([
            'company_id' => $companyId ?: null,
            'party_name' => trim((string) $this->party_name),
            'party_type_id' => $this->party_type_id ?: null,
            'mobile' => $this->blankToNull($this->mobile),
            'email' => $this->blankToNull($this->email),
            'address' => $this->blankToNull($this->address),
            'credit_limit' => $this->normalizeNullableMoney($this->credit_limit),
            'payment_terms' => $this->blankToNull($this->payment_terms),
            'department' => $this->blankToNull($this->department),
            'designation' => $this->blankToNull($this->designation),
            'salary_amount' => $this->normalizeNullableMoney($this->salary_amount),
            'ownership_percentage' => $this->normalizeNullableMoney($this->ownership_percentage),
            'contact_info' => $this->blankToNull($this->contact_info),
            // Sub Type is hidden from Party Setup. Preserve legacy values on update; new parties store null.
            // It remains classification-only and never drives accounting.
            'sub_type' => $this->exists('sub_type')
                ? $this->normalizeSubType($this->input('sub_type'))
                : $party?->sub_type,
            'receivable_ledger_account_id' => $mappings[PartyLedgerMapping::PURPOSE_RECEIVABLE] ?? null,
            'payable_ledger_account_id' => $mappings[PartyLedgerMapping::PURPOSE_PAYABLE] ?? null,
            'capital_ledger_account_id' => $mappings[PartyLedgerMapping::PURPOSE_CAPITAL] ?? null,
            'payable_capital_ledger_account_id' => $mappings[PartyLedgerMapping::PURPOSE_PAYABLE]
                ?? $mappings[PartyLedgerMapping::PURPOSE_CAPITAL]
                ?? null,
            'ledger_mappings' => collect($mappings)
                ->map(fn (int $ledgerId, string $purpose) => [
                    'purpose' => $purpose,
                    'chart_of_account_id' => $ledgerId,
                ])
                ->values()
                ->all(),
            // Compatibility field used by opening balance and older reports.
            'linked_ledger_account_id' => $primaryLedgerId,
            'default_ledger_nature' => $defaultLedgerNature,
            'opening_balance' => $openingBalance,
            'opening_balance_type' => $openingBalance > 0
                ? $this->openingSideFromLedgerNature($defaultLedgerNature, $primaryLedger)
                : null,
            'notes' => $this->blankToNull($this->notes),
            'status' => $this->status ?: 'Active',
        ]);
    }

    public function rules(): array
    {
        /** @var Party|null $party */
        $party = $this->route('party');
        $partyId = $party?->id;
        $companyId = (int) ($this->input('company_id') ?: 0);

        $companyUnique = fn (string $column) => Rule::unique('parties', $column)
            ->where(function ($query) use ($companyId) {
                $companyId > 0
                    ? $query->where('company_id', $companyId)
                    : $query->whereNull('company_id');

                return $query->whereNull('deleted_at');
            })
            ->ignore($partyId);

        $postingLedgerExists = Rule::exists('chart_of_accounts', 'id')
            ->where(function ($query) use ($companyId) {
                $query->where('status', 'Active')
                    ->where('account_level', 'Ledger')
                    ->where('posting_allowed', true)
                    ->whereNull('deleted_at');

                if ($companyId > 0) {
                    $query->where(function ($scope) use ($companyId) {
                        $scope->where('company_id', $companyId)->orWhereNull('company_id');
                    });
                }
            });

        return [
            'company_id' => ['nullable', 'integer', 'exists:companies,id'],
            'party_name' => ['required', 'string', 'max:255', $companyUnique('party_name')],
            'party_type_id' => [
                'required',
                'integer',
                Rule::exists('party_types', 'id')->where(fn ($query) => $query->where('status', 'Active')),
            ],
            'sub_type' => ['nullable', 'string', 'max:100'],
            'mobile' => ['nullable', 'string', 'max:50', $companyUnique('mobile')],
            'email' => ['nullable', 'email', 'max:255', $companyUnique('email')],
            'address' => ['nullable', 'string', 'max:1000'],
            'credit_limit' => ['nullable', 'numeric', 'min:0'],
            'payment_terms' => ['nullable', 'string', 'max:100'],
            'department' => ['nullable', 'string', 'max:100'],
            'designation' => ['nullable', 'string', 'max:100'],
            'salary_amount' => ['nullable', 'numeric', 'min:0'],
            'ownership_percentage' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'contact_info' => ['nullable', 'string', 'max:255'],
            'receivable_ledger_account_id' => ['nullable', 'integer', $postingLedgerExists],
            'payable_ledger_account_id' => ['nullable', 'integer', $postingLedgerExists],
            'capital_ledger_account_id' => ['nullable', 'integer', $postingLedgerExists],
            'payable_capital_ledger_account_id' => ['nullable', 'integer', $postingLedgerExists],
            'linked_ledger_account_id' => ['nullable', 'integer', $postingLedgerExists],
            'ledger_mappings' => ['nullable', 'array'],
            'ledger_mappings.*.purpose' => ['required', Rule::in(PartyLedgerMapping::PURPOSES), 'distinct'],
            'ledger_mappings.*.chart_of_account_id' => ['required', 'integer', $postingLedgerExists],
            'default_ledger_nature' => ['required', Rule::in(PartyAccountingProfile::NATURES)],
            'opening_balance' => ['nullable', 'numeric', 'min:0'],
            'opening_balance_type' => ['nullable', Rule::in(['Debit', 'Credit'])],
            'notes' => ['nullable', 'string', 'max:1000'],
            'status' => ['required', Rule::in(['Active', 'Inactive'])],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $mappings = collect($this->input('ledger_mappings', []));

            if ($this->input('status') === 'Active' && $mappings->isEmpty()) {
                $validator->errors()->add('ledger_mappings', 'At least one party ledger mapping is required for an active party.');
                return;
            }

            $requiredPurpose = PartyAccountingProfile::purposeFromNature($this->input('default_ledger_nature'));
            if ($this->input('status') === 'Active'
                && $requiredPurpose !== PartyLedgerMapping::PURPOSE_GENERAL
                && ! $mappings->contains(fn (array $mapping) => ($mapping['purpose'] ?? null) === $requiredPurpose)) {
                $validator->errors()->add(
                    'ledger_mappings',
                    'The selected ledger nature requires a ' . str_replace('_', ' ', $requiredPurpose) . ' ledger mapping.'
                );
            }

            foreach ($mappings as $index => $mapping) {
                $purpose = (string) ($mapping['purpose'] ?? '');
                $ledgerId = (int) ($mapping['chart_of_account_id'] ?? 0);
                $ledger = ChartOfAccount::query()->with('accountType')->find($ledgerId);

                if (! $ledger) {
                    continue;
                }

                $this->validateLedgerForPurpose($validator, $ledger, $purpose, $index);
            }

            if ((float) $this->input('opening_balance', 0) > 0 && ! $this->input('linked_ledger_account_id')) {
                $validator->errors()->add('opening_balance', 'Opening Balance requires a valid primary party ledger mapping.');
            }
        });
    }

    public function messages(): array
    {
        return [
            'party_name.required' => 'Party Name is required.',
            'party_name.unique' => 'This Party Name already exists for the current company.',
            'party_type_id.required' => 'Party Type is required.',
            'party_type_id.exists' => 'Selected Party Type is not active.',
            'mobile.unique' => 'This mobile number is already used by another party in the current company.',
            'email.unique' => 'This email is already used by another party in the current company.',
            'ledger_mappings.*.purpose.distinct' => 'A ledger purpose can be configured only once for a party.',
            'ledger_mappings.*.chart_of_account_id.exists' => 'Each party mapping must use an active posting ledger from the current company.',
            'default_ledger_nature.in' => 'Primary Accounting Nature must be Receivable, Payable, Advance Paid, Advance Received, Capital, or No Effect.',
            'opening_balance.numeric' => 'Opening Balance must be a valid number.',
            'opening_balance.min' => 'Opening Balance cannot be negative.',
            'status.in' => 'Status must be Active or Inactive.',
        ];
    }

    private function validateLedgerForPurpose(
        Validator $validator,
        ChartOfAccount $ledger,
        string $purpose,
        int $index
    ): void {
        $field = "ledger_mappings.{$index}.chart_of_account_id";
        $accountType = $ledger->accountType?->name;
        $normalBalance = $ledger->normal_balance ?: $ledger->accountType?->normal_balance;

        if ((bool) $ledger->is_cash_bank) {
            $validator->errors()->add($field, 'Party mappings cannot use a Cash/Bank ledger.');
            return;
        }

        if (in_array($accountType, ['Income', 'Expense'], true)) {
            $validator->errors()->add($field, 'Party mappings must use Asset, Liability, or Equity ledgers.');
            return;
        }

        if (in_array($purpose, [PartyLedgerMapping::PURPOSE_RECEIVABLE, PartyLedgerMapping::PURPOSE_ADVANCE_PAID], true)
            && ($accountType !== 'Asset' || $normalBalance !== 'Debit')) {
            $validator->errors()->add($field, 'Receivable and Advance Paid mappings must use a Debit-normal Asset ledger.');
        }

        if (in_array($purpose, [
            PartyLedgerMapping::PURPOSE_PAYABLE,
            PartyLedgerMapping::PURPOSE_ADVANCE_RECEIVED,
            PartyLedgerMapping::PURPOSE_LOAN_PAYABLE,
            PartyLedgerMapping::PURPOSE_SALARY_PAYABLE,
        ], true) && ($accountType !== 'Liability' || $normalBalance !== 'Credit')) {
            $validator->errors()->add($field, 'Payable mappings must use a Credit-normal Liability ledger.');
        }

        if ($purpose === PartyLedgerMapping::PURPOSE_CAPITAL
            && ($accountType !== 'Equity' || $normalBalance !== 'Credit')) {
            $validator->errors()->add($field, 'Capital mappings must use a Credit-normal Equity ledger.');
        }
    }

    /** @return array<string, int> */
    private function existingAdvancedMappings(?Party $party): array
    {
        if (! $party) {
            return [];
        }

        return $party->ledgerMappings
            ->filter(fn ($mapping) => $mapping->chart_of_account_id)
            ->mapWithKeys(fn ($mapping) => [$mapping->mapping_purpose => (int) $mapping->chart_of_account_id])
            ->all();
    }

    /** @param array<string, int> $mappings */
    private function mergeSubmittedMappingArray(array &$mappings): void
    {
        if (! $this->exists('ledger_mappings') || ! is_array($this->input('ledger_mappings'))) {
            return;
        }

        $mappings = [];

        foreach ($this->input('ledger_mappings', []) as $mapping) {
            if (! is_array($mapping)) {
                continue;
            }

            $purpose = strtolower(trim(str_replace([' ', '-'], '_', (string) ($mapping['purpose'] ?? ''))));
            $ledgerId = $this->nullableInteger($mapping['chart_of_account_id'] ?? null);

            if (in_array($purpose, PartyLedgerMapping::PURPOSES, true) && $ledgerId) {
                $mappings[$purpose] = $ledgerId;
            }
        }
    }

    private function defaultLedgerForCompany(?PartyType $partyType, int $companyId): ?int
    {
        $ledgerId = (int) ($partyType?->default_ledger_account_id ?: 0);
        if ($ledgerId <= 0) {
            return null;
        }

        return ChartOfAccount::query()
            ->whereKey($ledgerId)
            ->where(function ($query) use ($companyId) {
                if ($companyId > 0) {
                    $query->where('company_id', $companyId)->orWhereNull('company_id');
                }
            })
            ->value('id');
    }

    private function openingSideFromLedgerNature(?string $nature, ?ChartOfAccount $ledger = null): ?string
    {
        return PartyAccountingProfile::openingSideForPurpose(
            PartyAccountingProfile::purposeFromNature($nature)
        ) ?: $ledger?->normal_balance ?: $ledger?->accountType?->normal_balance;
    }

    private function normalizeSubType(mixed $value): ?string
    {
        $value = Str::squish((string) $value);

        return $value === '' ? null : $value;
    }

    private function blankToNull(mixed $value): ?string
    {
        $value = trim((string) $value);
        return $value === '' ? null : $value;
    }

    private function nullableInteger(mixed $value): ?int
    {
        if ($value === null || trim((string) $value) === '') {
            return null;
        }

        $value = (int) $value;
        return $value > 0 ? $value : null;
    }

    private function normalizeMoney(mixed $value): float
    {
        if ($value === null || $value === '') {
            return 0.00;
        }

        return round((float) str_replace(',', '', (string) $value), 2);
    }

    private function normalizeNullableMoney(mixed $value): ?float
    {
        if ($value === null || trim((string) $value) === '') {
            return null;
        }

        return round((float) str_replace(',', '', (string) $value), 2);
    }
}
