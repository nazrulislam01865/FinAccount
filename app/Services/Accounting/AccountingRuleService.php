<?php

namespace App\Services\Accounting;

use App\Models\AccountingOption;
use App\Models\AccountingRule;
use App\Models\AccountingRuleLine;
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
            ->with('lines')
            ->where('company_id', $companyId)
            ->orderBy('category')
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

            return $rule->load('lines');
        }, attempts: 5);
    }

    /** @param array<string, mixed> $data */
    public function update(AccountingRule $rule, array $data): AccountingRule
    {
        $this->validateConfiguration($data, (int) $rule->company_id, $rule);

        DB::transaction(function () use ($rule, $data): void {
            $this->automaticCodeService->lockCompany((int) $rule->company_id);
            $data['code'] = (string) $data['name'] === (string) $rule->name
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

        return $rule->refresh()->load('lines');
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

        $duplicate = AccountingRule::query()
            ->where('company_id', $companyId)
            ->where('category', $type)
            ->where('settlement_type', $settlement)
            ->when($ignore, fn ($query) => $query->whereKeyNot($ignore->id))
            ->exists();

        if ($duplicate) {
            throw ValidationException::withMessages([
                'settlement_type' => 'A rule template already exists for this transaction type and payment type.',
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
        $template = $this->template($type, $settlement);
        $lines = $template['lines'];
        $firstDebit = collect($lines)->firstWhere('line_side', AccountingRuleLine::SIDE_DEBIT);
        $firstCredit = collect($lines)->firstWhere('line_side', AccountingRuleLine::SIDE_CREDIT);

        return [
            'rule' => [
                'code' => trim((string) $data['code']),
                'name' => trim((string) $data['name']),
                'category' => $type,
                'settlement_type' => $settlement,
                'debit_source' => $firstDebit['account_source'],
                'credit_source' => $firstCredit['account_source'],
                'party_required' => $template['party_required'],
                'party_type' => $template['party_type'],
                'money_required' => $template['money_required'],
                'generates_invoice' => TransactionTypes::isSale($type),
                'invoice_title' => TransactionTypes::isSale($type) ? 'Sales Invoice' : null,
                'is_active' => (bool) $data['is_active'],
            ],
            'lines' => $lines,
        ];
    }

    /** @return array{party_required:bool,party_type:string,money_required:bool,lines:array<int,array{line_side:string,account_source:string,amount_basis:string}>} */
    private function template(string $type, string $settlement): array
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

        return TransactionTypes::flow($type, $metadata) === 'incoming'
            ? $incoming((string) ($metadata['party_type'] ?? 'Any'))
            : $outgoing((string) ($metadata['party_type'] ?? 'Any'));
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
    public function amountBasisLabels(): array
    {
        return [
            AccountingRuleLine::BASIS_TOTAL => 'Total Amount',
            AccountingRuleLine::BASIS_PAID => 'Paid/Received Now',
            AccountingRuleLine::BASIS_DUE => 'Remaining Due',
        ];
    }
}
