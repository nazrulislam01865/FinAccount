<?php

namespace App\Services\Accounting;

use App\Models\AccountingOption;
use App\Models\AccountingRule;
use App\Models\AccountingRuleLine;
use App\Models\TransactionHead;
use App\Support\TransactionTypes;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AccountingRuleService
{
    public function __construct(
        private readonly AccountingOptionService $optionService,
        private readonly AutomaticCodeService $automaticCodeService,
    ) {}

    /** @return array<string, mixed> */
    public function pageData(int $companyId): array
    {
        $transactionCategories = $this->optionService->forGroup(AccountingOption::GROUP_TRANSACTION_CATEGORY);

        return [
            'rules' => $this->allForCompany($companyId),
            'transactionHeads' => TransactionHead::query()
                ->where('company_id', $companyId)
                ->orderBy('category')
                ->orderBy('name')
                ->get(),
            'transactionCategories' => $transactionCategories,
            'categoryLabels' => $this->optionService->labels(AccountingOption::GROUP_TRANSACTION_CATEGORY),
            'settlementTypes' => $this->optionService->forGroup(AccountingOption::GROUP_SETTLEMENT_TYPE),
            'settlementLabels' => $this->optionService->labels(AccountingOption::GROUP_SETTLEMENT_TYPE),
            'transactionTypeDefinitions' => $transactionCategories->mapWithKeys(fn (AccountingOption $option): array => [
                $option->value => TransactionTypes::configuredDefinition(
                    $option->value,
                    is_array($option->metadata) ? $option->metadata : [],
                    $option->label,
                ),
            ])->all(),
            'sourceLabels' => $this->optionService->labels(AccountingOption::GROUP_ACCOUNTING_SOURCE),
            'amountBasisLabels' => $this->amountBasisLabels(),
        ];
    }

    /** @return Collection<int, AccountingRule> */
    public function allForCompany(int $companyId): Collection
    {
        return AccountingRule::query()
            ->with(['lines', 'transactionHead'])
            ->where('company_id', $companyId)
            ->orderBy('category')
            ->orderByRaw('CASE WHEN transaction_head_id IS NULL THEN 0 ELSE 1 END')
            ->orderBy('transaction_head_id')
            ->orderBy('settlement_type')
            ->orderBy('code')
            ->get();
    }

    /** @param array<string, mixed> $data */
    public function create(array $data, int $companyId): AccountingRule
    {
        $this->validateConfiguration($data, $companyId);

        return DB::transaction(function () use ($data, $companyId): AccountingRule {
            $this->automaticCodeService->lockCompany($companyId);
            $data['code'] = $this->automaticCodeService->accountingRuleCode($companyId, (string) $data['name']);
            $normalized = $this->normalized($data);
            $rule = AccountingRule::query()->create([
                'company_id' => $companyId,
                ...$normalized['rule'],
            ]);
            $this->syncLines($rule, $normalized['lines']);

            return $rule->load(['lines', 'transactionHead']);
        }, attempts: 5);
    }

    /** @param array<string, mixed> $data */
    public function update(AccountingRule $rule, array $data): AccountingRule
    {
        $this->validateConfiguration($data, (int) $rule->company_id, $rule);

        DB::transaction(function () use ($rule, $data): void {
            $this->automaticCodeService->lockCompany((int) $rule->company_id);
            $data['code'] = str_starts_with(strtoupper((string) $rule->code), 'SYS-FEED-RULE-')
                || (string) $data['name'] === (string) $rule->name
                ? $rule->code
                : $this->automaticCodeService->accountingRuleCode(
                    (int) $rule->company_id,
                    (string) $data['name'],
                    (int) $rule->id,
                );
            $normalized = $this->normalized($data);
            $rule->update($normalized['rule']);
            $this->syncLines($rule, $normalized['lines']);
        }, attempts: 5);

        return $rule->refresh()->load(['lines', 'transactionHead']);
    }


    /**
     * @param Collection<int, AccountingRule> $rules
     */
    public function setActive(Collection $rules, bool $active): int
    {
        if ($rules->isEmpty()) {
            return 0;
        }

        if ($active) {
            $this->validateBulkActivation($rules);
        }

        return DB::transaction(function () use ($rules, $active): int {
            $ids = $rules->pluck('id')->map(fn ($id): int => (int) $id)->all();

            AccountingRule::query()
                ->whereIn('id', $ids)
                ->lockForUpdate()
                ->get(['id']);

            return AccountingRule::query()
                ->whereIn('id', $ids)
                ->where('is_active', '!=', $active)
                ->update(['is_active' => $active]);
        }, attempts: 5);
    }

    /**
     * @param Collection<int, AccountingRule> $rules
     */
    private function validateBulkActivation(Collection $rules): void
    {
        $activeCategories = AccountingOption::query()
            ->forGroup(AccountingOption::GROUP_TRANSACTION_CATEGORY)
            ->where('is_active', true)
            ->get()
            ->keyBy('value');
        $activeSettlements = AccountingOption::query()
            ->forGroup(AccountingOption::GROUP_SETTLEMENT_TYPE)
            ->where('is_active', true)
            ->pluck('value')
            ->all();
        $activePartyTypes = AccountingOption::query()
            ->forGroup(AccountingOption::GROUP_RULE_PARTY_TYPE)
            ->where('is_active', true)
            ->pluck('value')
            ->all();
        $companyId = (int) $rules->first()->company_id;
        $duplicateKeys = AccountingRule::query()
            ->where('company_id', $companyId)
            ->get(['id', 'transaction_head_id', 'category', 'settlement_type'])
            ->groupBy(fn (AccountingRule $rule): string => $this->scopeKey($rule->category, $rule->settlement_type, $rule->transaction_head_id))
            ->filter(fn (Collection $group): bool => $group->count() > 1)
            ->keys()
            ->all();
        $transactionHeads = TransactionHead::query()
            ->where('company_id', $companyId)
            ->get()
            ->keyBy('id');
        $validSides = [AccountingRuleLine::SIDE_DEBIT, AccountingRuleLine::SIDE_CREDIT];
        $validSources = [
            AccountingRule::SOURCE_SELECTED_MONEY,
            AccountingRule::SOURCE_HEAD_ACCOUNT,
            AccountingRule::SOURCE_PARTY_RECEIVABLE,
            AccountingRule::SOURCE_PARTY_PAYABLE,
        ];
        $validBases = [
            AccountingRuleLine::BASIS_TOTAL,
            AccountingRuleLine::BASIS_PAID,
            AccountingRuleLine::BASIS_DUE,
        ];

        $invalid = $rules->filter(function (AccountingRule $rule) use (
            $activeCategories,
            $activeSettlements,
            $activePartyTypes,
            $duplicateKeys,
            $transactionHeads,
            $validSides,
            $validSources,
            $validBases,
        ): bool {
            $category = (string) $rule->category;
            $settlement = (string) $rule->settlement_type;
            $categoryOption = $activeCategories->get($category);
            $lines = $rule->lines;

            $scopeKey = $this->scopeKey($category, $settlement, $rule->transaction_head_id);
            $transactionHead = $rule->transaction_head_id
                ? $transactionHeads->get((int) $rule->transaction_head_id)
                : null;

            if (! $categoryOption
                || ! in_array($settlement, $activeSettlements, true)
                || in_array($scopeKey, $duplicateKeys, true)
                || ($rule->transaction_head_id && (! $transactionHead
                    || ! $transactionHead->is_active
                    || strcasecmp((string) $transactionHead->category, $category) !== 0))) {
                return true;
            }

            $definition = TransactionTypes::configuredDefinition(
                $category,
                is_array($categoryOption->metadata) ? $categoryOption->metadata : [],
                $categoryOption->label,
            );
            $allowedSettlements = array_values((array) ($definition['allowed_settlements'] ?? []));
            $flow = (string) ($definition['flow'] ?? '');

            if ($allowedSettlements !== [] && ! in_array($settlement, $allowedSettlements, true)) {
                return true;
            }

            if ($flow === TransactionTypes::FLOW_NON_CASH
                && (! $rule->transaction_head_id || $rule->money_required)) {
                return true;
            }

            if ($lines->isEmpty()
                || ! $lines->contains('line_side', AccountingRuleLine::SIDE_DEBIT)
                || ! $lines->contains('line_side', AccountingRuleLine::SIDE_CREDIT)) {
                return true;
            }

            if ($lines->contains(fn (AccountingRuleLine $line): bool =>
                ! in_array($line->line_side, $validSides, true)
                || ! in_array($line->account_source, $validSources, true)
                || ! in_array($line->amount_basis, $validBases, true)
            )) {
                return true;
            }

            $partyType = (string) ($rule->party_type ?: 'Any');

            return ! in_array($partyType, $activePartyTypes, true);
        });

        if ($invalid->isNotEmpty()) {
            $names = $invalid
                ->take(5)
                ->map(fn (AccountingRule $rule): string => $rule->code.' — '.$rule->name)
                ->implode(', ');

            throw ValidationException::withMessages([
                'record_ids' => 'These accounting rules cannot be activated because their transaction type, payment type, party type, or posting lines are incomplete or inactive: '.$names.'. Edit and repair them first.',
            ]);
        }
    }

    public function delete(AccountingRule $rule): void
    {
        if ($rule->transactionHeads()->exists()) {
            throw ValidationException::withMessages([
                'accounting_rule' => 'This legacy rule is still referenced by a transaction head. Edit that head once to remove the old reference before deleting the rule.',
            ]);
        }

        DB::transaction(fn () => $rule->delete(), attempts: 5);
    }

    /** @param array<string, mixed> $data */
    private function validateConfiguration(array $data, int $companyId, ?AccountingRule $ignore = null): void
    {
        $type = (string) $data['category'];
        $settlement = (string) $data['settlement_type'];

        if (! in_array($settlement, TransactionTypes::settlementCodes(), true)) {
            throw ValidationException::withMessages([
                'settlement_type' => 'This payment type is not supported by the system.',
            ]);
        }

        $transactionHeadId = filled($data['transaction_head_id'] ?? null)
            ? (int) $data['transaction_head_id']
            : null;

        if ($transactionHeadId) {
            $transactionHead = TransactionHead::query()
                ->where('company_id', $companyId)
                ->find($transactionHeadId);

            if (! $transactionHead || strcasecmp((string) $transactionHead->category, $type) !== 0) {
                throw ValidationException::withMessages([
                    'transaction_head_id' => 'Select a transaction head that belongs to the selected transaction type.',
                ]);
            }
        }

        if ($ignore && str_starts_with(strtoupper((string) $ignore->code), 'SYS-FEED-RULE-')) {
            if (
                (int) ($ignore->transaction_head_id ?? 0) !== (int) ($transactionHeadId ?? 0)
                || strcasecmp((string) $ignore->category, $type) !== 0
                || (string) $ignore->settlement_type !== $settlement
            ) {
                throw ValidationException::withMessages([
                    'transaction_head_id' => 'The Feed module rule scope and payment type are protected. You may rename it or change its active status, but its Feed head mapping cannot be changed.',
                ]);
            }
        }

        $duplicate = AccountingRule::query()
            ->where('company_id', $companyId)
            ->where('category', $type)
            ->where('settlement_type', $settlement)
            ->when(
                $transactionHeadId,
                fn ($query) => $query->where('transaction_head_id', $transactionHeadId),
                fn ($query) => $query->whereNull('transaction_head_id'),
            )
            ->when($ignore, fn ($query) => $query->whereKeyNot($ignore->id))
            ->exists();

        if ($duplicate) {
            throw ValidationException::withMessages([
                'settlement_type' => $transactionHeadId
                    ? 'An accounting rule already exists for this transaction head and payment type.'
                    : 'An accounting rule already exists for this transaction type and payment type.',
            ]);
        }
    }

    /**
     * @param array<string, mixed> $data
     * @return array{rule:array<string,mixed>,lines:array<int,array{line_side:string,account_source:string,amount_basis:string}>}
     */
    private function normalized(array $data): array
    {
        $type = (string) $data['category'];
        $settlement = (string) $data['settlement_type'];
        $transactionHeadId = filled($data['transaction_head_id'] ?? null)
            ? (int) $data['transaction_head_id']
            : null;
        $transactionHead = $transactionHeadId
            ? TransactionHead::query()->with('postingAccount')->find($transactionHeadId)
            : null;
        $isFeedRule = $transactionHead
            && str_starts_with(strtoupper((string) $transactionHead->code), 'SYS-FEED-');
        $template = $this->template($type, $settlement, $transactionHead);
        $lines = $template['lines'];
        $firstDebit = collect($lines)->firstWhere('line_side', AccountingRuleLine::SIDE_DEBIT);
        $firstCredit = collect($lines)->firstWhere('line_side', AccountingRuleLine::SIDE_CREDIT);

        return [
            'rule' => [
                'transaction_head_id' => $transactionHeadId,
                'code' => trim((string) $data['code']),
                'name' => trim((string) $data['name']),
                'category' => $type,
                'settlement_type' => $settlement,
                'debit_source' => $firstDebit['account_source'],
                'credit_source' => $firstCredit['account_source'],
                'party_required' => $isFeedRule ? true : $template['party_required'],
                'party_type' => $isFeedRule
                    ? ($type === TransactionTypes::SALE ? 'Customer' : 'Supplier')
                    : $template['party_type'],
                'money_required' => $template['money_required'],
                'generates_invoice' => TransactionTypes::isSale($type),
                'invoice_title' => TransactionTypes::isSale($type)
                    ? ($isFeedRule ? 'Feed Sales Invoice' : 'Sales Invoice')
                    : null,
                'is_active' => (bool) $data['is_active'],
            ],
            'lines' => $lines,
        ];
    }

    /** @return array{party_required:bool,party_type:string,money_required:bool,lines:array<int,array{line_side:string,account_source:string,amount_basis:string}>} */
    private function template(string $type, string $settlement, ?TransactionHead $transactionHead = null): array
    {
        $debit = AccountingRuleLine::SIDE_DEBIT;
        $credit = AccountingRuleLine::SIDE_CREDIT;
        $total = AccountingRuleLine::BASIS_TOTAL;
        $paid = AccountingRuleLine::BASIS_PAID;
        $due = AccountingRuleLine::BASIS_DUE;
        $money = AccountingRule::SOURCE_SELECTED_MONEY;
        $head = AccountingRule::SOURCE_HEAD_ACCOUNT;
        $receivable = AccountingRule::SOURCE_PARTY_RECEIVABLE;
        $payable = AccountingRule::SOURCE_PARTY_PAYABLE;

        $incoming = static function (string $partyType, bool $partyRequiredForCash = false) use (
            $settlement,
            $debit,
            $credit,
            $total,
            $paid,
            $due,
            $money,
            $head,
            $receivable,
        ): array {
            return match ($settlement) {
                TransactionTypes::CASH => [
                    'party_required' => $partyRequiredForCash,
                    'party_type' => $partyRequiredForCash ? $partyType : 'Any',
                    'money_required' => true,
                    'lines' => [
                        ['line_side' => $debit, 'account_source' => $money, 'amount_basis' => $total],
                        ['line_side' => $credit, 'account_source' => $head, 'amount_basis' => $total],
                    ],
                ],
                TransactionTypes::CREDIT => [
                    'party_required' => true,
                    'party_type' => $partyType,
                    'money_required' => false,
                    'lines' => [
                        ['line_side' => $debit, 'account_source' => $receivable, 'amount_basis' => $total],
                        ['line_side' => $credit, 'account_source' => $head, 'amount_basis' => $total],
                    ],
                ],
                TransactionTypes::PARTIAL => [
                    'party_required' => true,
                    'party_type' => $partyType,
                    'money_required' => true,
                    'lines' => [
                        ['line_side' => $debit, 'account_source' => $money, 'amount_basis' => $paid],
                        ['line_side' => $debit, 'account_source' => $receivable, 'amount_basis' => $due],
                        ['line_side' => $credit, 'account_source' => $head, 'amount_basis' => $total],
                    ],
                ],
                default => throw ValidationException::withMessages([
                    'settlement_type' => 'No standard accounting template is available for this payment type.',
                ]),
            };
        };

        $outgoing = static function (string $partyType, bool $partyRequiredForCash = false) use (
            $settlement,
            $debit,
            $credit,
            $total,
            $paid,
            $due,
            $money,
            $head,
            $payable,
        ): array {
            return match ($settlement) {
                TransactionTypes::CASH => [
                    'party_required' => $partyRequiredForCash,
                    'party_type' => $partyRequiredForCash ? $partyType : 'Any',
                    'money_required' => true,
                    'lines' => [
                        ['line_side' => $debit, 'account_source' => $head, 'amount_basis' => $total],
                        ['line_side' => $credit, 'account_source' => $money, 'amount_basis' => $total],
                    ],
                ],
                TransactionTypes::CREDIT => [
                    'party_required' => true,
                    'party_type' => $partyType,
                    'money_required' => false,
                    'lines' => [
                        ['line_side' => $debit, 'account_source' => $head, 'amount_basis' => $total],
                        ['line_side' => $credit, 'account_source' => $payable, 'amount_basis' => $total],
                    ],
                ],
                TransactionTypes::PARTIAL => [
                    'party_required' => true,
                    'party_type' => $partyType,
                    'money_required' => true,
                    'lines' => [
                        ['line_side' => $debit, 'account_source' => $head, 'amount_basis' => $total],
                        ['line_side' => $credit, 'account_source' => $money, 'amount_basis' => $paid],
                        ['line_side' => $credit, 'account_source' => $payable, 'amount_basis' => $due],
                    ],
                ],
                default => throw ValidationException::withMessages([
                    'settlement_type' => 'No standard accounting template is available for this payment type.',
                ]),
            };
        };

        if ($type === TransactionTypes::SALE) {
            return $incoming('Customer');
        }

        if (in_array($type, [TransactionTypes::PURCHASE, TransactionTypes::ASSET_PURCHASE], true)) {
            return $outgoing('Supplier');
        }

        if ($type === TransactionTypes::EXPENSE) {
            return $outgoing('Any');
        }

        if ($type === TransactionTypes::CUSTOMER_COLLECTION) {
            return match ($settlement) {
                TransactionTypes::CASH => [
                    'party_required' => true,
                    'party_type' => 'Customer',
                    'money_required' => true,
                    'lines' => [
                        ['line_side' => $debit, 'account_source' => $money, 'amount_basis' => $total],
                        ['line_side' => $credit, 'account_source' => $receivable, 'amount_basis' => $total],
                    ],
                ],
                TransactionTypes::CREDIT => [
                    'party_required' => true,
                    'party_type' => 'Customer',
                    'money_required' => false,
                    'lines' => [
                        ['line_side' => $debit, 'account_source' => $head, 'amount_basis' => $total],
                        ['line_side' => $credit, 'account_source' => $receivable, 'amount_basis' => $total],
                    ],
                ],
                default => [
                    'party_required' => true,
                    'party_type' => 'Customer',
                    'money_required' => true,
                    'lines' => [
                        ['line_side' => $debit, 'account_source' => $money, 'amount_basis' => $paid],
                        ['line_side' => $debit, 'account_source' => $head, 'amount_basis' => $due],
                        ['line_side' => $credit, 'account_source' => $receivable, 'amount_basis' => $total],
                    ],
                ],
            };
        }

        if ($type === TransactionTypes::SUPPLIER_PAYMENT) {
            return match ($settlement) {
                TransactionTypes::CASH => [
                    'party_required' => true,
                    'party_type' => 'Supplier',
                    'money_required' => true,
                    'lines' => [
                        ['line_side' => $debit, 'account_source' => $payable, 'amount_basis' => $total],
                        ['line_side' => $credit, 'account_source' => $money, 'amount_basis' => $total],
                    ],
                ],
                TransactionTypes::CREDIT => [
                    'party_required' => true,
                    'party_type' => 'Supplier',
                    'money_required' => false,
                    'lines' => [
                        ['line_side' => $debit, 'account_source' => $payable, 'amount_basis' => $total],
                        ['line_side' => $credit, 'account_source' => $head, 'amount_basis' => $total],
                    ],
                ],
                default => [
                    'party_required' => true,
                    'party_type' => 'Supplier',
                    'money_required' => true,
                    'lines' => [
                        ['line_side' => $debit, 'account_source' => $payable, 'amount_basis' => $total],
                        ['line_side' => $credit, 'account_source' => $money, 'amount_basis' => $paid],
                        ['line_side' => $credit, 'account_source' => $head, 'amount_basis' => $due],
                    ],
                ],
            };
        }

        if ($type === TransactionTypes::OWNER_INVESTMENT) {
            return $incoming('Owner', true);
        }

        if ($type === TransactionTypes::OWNER_WITHDRAWAL) {
            return $outgoing('Owner', true);
        }

        if ($type === TransactionTypes::LOAN_RECEIVED) {
            return $incoming('Lender', true);
        }

        if (in_array($type, [TransactionTypes::LOAN_REPAYMENT, TransactionTypes::LOAN_INTEREST_PAYMENT], true)) {
            return $outgoing('Lender', true);
        }

        $transactionType = AccountingOption::query()
            ->forGroup(AccountingOption::GROUP_TRANSACTION_CATEGORY)
            ->where('value', $type)
            ->first();

        if (! $transactionType) {
            throw ValidationException::withMessages([
                'category' => 'The selected transaction type is not available.',
            ]);
        }

        $metadata = is_array($transactionType->metadata) ? $transactionType->metadata : [];
        $partyType = (string) ($metadata['party_type'] ?? 'Any');
        $flow = TransactionTypes::flow($type, $metadata);

        return match ($flow) {
            TransactionTypes::FLOW_INCOMING => $incoming($partyType),
            TransactionTypes::FLOW_OUTGOING => $outgoing($partyType),
            TransactionTypes::FLOW_TRANSFER => match ($settlement) {
                TransactionTypes::CASH => [
                    'party_required' => false,
                    'party_type' => 'Any',
                    'money_required' => true,
                    'lines' => [
                        ['line_side' => $debit, 'account_source' => $head, 'amount_basis' => $total],
                        ['line_side' => $credit, 'account_source' => $money, 'amount_basis' => $total],
                    ],
                ],
                default => throw ValidationException::withMessages([
                    'settlement_type' => 'Transfer direction supports only a fully paid/received transaction because no due balance is created.',
                ]),
            },
            TransactionTypes::FLOW_NON_CASH => $this->nonCashTemplate(
                $settlement,
                $transactionHead,
                $partyType,
                $debit,
                $credit,
                $total,
                $head,
                $receivable,
                $payable,
            ),
            default => throw ValidationException::withMessages([
                'category' => 'The selected transaction direction is not supported.',
            ]),
        };
    }


    /**
     * @return array{party_required:bool,party_type:string,money_required:bool,lines:array<int,array{line_side:string,account_source:string,amount_basis:string}>}
     */
    private function nonCashTemplate(
        string $settlement,
        ?TransactionHead $transactionHead,
        string $configuredPartyType,
        string $debit,
        string $credit,
        string $total,
        string $headSource,
        string $receivableSource,
        string $payableSource,
    ): array {
        if ($settlement !== TransactionTypes::CASH) {
            throw ValidationException::withMessages([
                'settlement_type' => 'Non-Cash transaction types support only the Fully paid/received payment type because no cash/bank movement or due split is created.',
            ]);
        }

        if (! $transactionHead) {
            throw ValidationException::withMessages([
                'transaction_head_id' => 'Select a Transaction Head Scope for Non-Cash rules. Non-Cash entries need a head-specific template so the posting COA is known.',
            ]);
        }

        $partyType = (string) ($transactionHead->party_type ?: $configuredPartyType ?: 'Any');

        if ($partyType === 'Any') {
            $partyType = $this->inferNonCashPartyType($transactionHead);
        }

        if ($partyType === 'Any') {
            throw ValidationException::withMessages([
                'transaction_head_id' => 'Set the selected Non-Cash transaction head party type to Customer, Supplier, Worker, Owner, Lender, or another payable party type before creating its accounting rule.',
            ]);
        }

        $postingAccountType = (string) ($transactionHead->postingAccount?->type ?? '');
        $debitNormalTypes = ['Asset', 'Expense'];
        $headIsDebitNormal = in_array($postingAccountType, $debitNormalTypes, true);
        $usesReceivable = strcasecmp($partyType, 'Customer') === 0;

        if ($usesReceivable) {
            $lines = $headIsDebitNormal
                ? [
                    ['line_side' => $debit, 'account_source' => $headSource, 'amount_basis' => $total],
                    ['line_side' => $credit, 'account_source' => $receivableSource, 'amount_basis' => $total],
                ]
                : [
                    ['line_side' => $debit, 'account_source' => $receivableSource, 'amount_basis' => $total],
                    ['line_side' => $credit, 'account_source' => $headSource, 'amount_basis' => $total],
                ];
        } else {
            $lines = $headIsDebitNormal
                ? [
                    ['line_side' => $debit, 'account_source' => $headSource, 'amount_basis' => $total],
                    ['line_side' => $credit, 'account_source' => $payableSource, 'amount_basis' => $total],
                ]
                : [
                    ['line_side' => $debit, 'account_source' => $payableSource, 'amount_basis' => $total],
                    ['line_side' => $credit, 'account_source' => $headSource, 'amount_basis' => $total],
                ];
        }

        return [
            'party_required' => true,
            'party_type' => $partyType,
            'money_required' => false,
            'lines' => $lines,
        ];
    }


    private function inferNonCashPartyType(TransactionHead $transactionHead): string
    {
        $searchText = strtolower(implode(' ', array_filter([
            $transactionHead->code,
            $transactionHead->name,
            $transactionHead->postingAccount?->name,
        ])));

        foreach (['bad debt', 'bad-debt', 'write off', 'write-off', 'writeoff', 'customer', 'receivable', 'debtor'] as $keyword) {
            if (str_contains($searchText, $keyword)) {
                return 'Customer';
            }
        }

        foreach (['supplier', 'vendor', 'payable', 'creditor'] as $keyword) {
            if (str_contains($searchText, $keyword)) {
                return 'Supplier';
            }
        }

        return 'Any';
    }

    /** @param array<int, array{line_side:string,account_source:string,amount_basis:string}> $lines */
    private function syncLines(AccountingRule $rule, array $lines): void
    {
        $rule->lines()->delete();

        foreach (array_values($lines) as $index => $line) {
            $rule->lines()->create([
                ...$line,
                'sort_order' => $index + 1,
            ]);
        }
    }

    /** @return array<string, string> */
    private function scopeKey(string $category, string $settlement, mixed $transactionHeadId): string
    {
        return strtolower($category).'|'.$settlement.'|'.($transactionHeadId ? (int) $transactionHeadId : 'GLOBAL');
    }

    public function amountBasisLabels(): array
    {
        return [
            AccountingRuleLine::BASIS_TOTAL => 'Total Amount',
            AccountingRuleLine::BASIS_PAID => 'Paid/Received Now',
            AccountingRuleLine::BASIS_DUE => 'Remaining Due',
        ];
    }
}
