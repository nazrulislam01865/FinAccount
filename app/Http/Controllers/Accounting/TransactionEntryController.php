<?php

namespace App\Http\Controllers\Accounting;

use App\Http\Controllers\Controller;
use App\Http\Requests\Accounting\StoreTransactionRequest;
use App\Models\AccountingOption;
use App\Models\ChartOfAccount;
use App\Models\MoneyAccount;
use App\Models\Party;
use App\Models\Transaction;
use App\Models\TransactionHead;
use App\Services\Accounting\AccountingOptionService;
use App\Services\Accounting\DecimalAmount;
use App\Services\Accounting\JournalBuilder;
use App\Services\Accounting\Reports\FinancialReportService;
use App\Services\Accounting\RuleMatcher;
use App\Services\Accounting\TransactionAttachmentService;
use App\Services\Accounting\TransactionEntryOptionService;
use App\Services\Accounting\TransactionPostingService;
use App\Services\Accounting\TransactionPartyResolver;
use App\Services\Accounting\TransactionSettlementService;
use App\Services\Company\CompanyAccountingPeriodService;
use App\Support\TransactionTypes;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class TransactionEntryController extends Controller
{
    public function __construct(
        private readonly JournalBuilder $journalBuilder,
        private readonly TransactionPostingService $transactionPostingService,
        private readonly TransactionSettlementService $settlementService,
        private readonly RuleMatcher $ruleMatcher,
        private readonly TransactionAttachmentService $transactionAttachmentService,
        private readonly DecimalAmount $decimalAmount,
        private readonly AccountingOptionService $optionService,
        private readonly TransactionEntryOptionService $entryOptionService,
        private readonly CompanyAccountingPeriodService $accountingPeriodService,
        private readonly TransactionPartyResolver $partyResolver,
        private readonly FinancialReportService $reportService,
    ) {}

    public function create(Request $request): View
    {
        $company = $request->user()->company;
        abort_unless($company, 404);

        $companyId = (int) $company->id;
        $transactionCategories = $this->optionService->forGroup(AccountingOption::GROUP_TRANSACTION_CATEGORY);
        $dueSettlementContext = $this->dueSettlementContext($request, $companyId);
        $requestedCategory = $dueSettlementContext['active']
            ? $dueSettlementContext['category']
            : strtoupper($request->string('category')->toString());
        $category = $transactionCategories->contains('value', $requestedCategory)
            ? $requestedCategory
            : ($transactionCategories->first()?->value ?? '');
        $categoryOption = $transactionCategories->firstWhere('value', $category);
        $categoryMetadata = is_array($categoryOption?->metadata) ? $categoryOption->metadata : [];

        return view('transactions.create', [
            'category' => $category,
            'categoryOption' => $categoryOption,
            'transactionCategories' => $transactionCategories,
            'transactionHeads' => $this->entryOptionService->transactionHeads($companyId, $category),
            'moneyAccounts' => $this->entryOptionService->moneyAccounts($companyId),
            'moneyKindLabels' => $this->optionService->labels(AccountingOption::GROUP_MONEY_ACCOUNT_KIND),
            'parties' => $this->entryOptionService->parties($companyId),
            'partyTypeLabels' => $this->optionService->labels(AccountingOption::GROUP_PARTY_TYPE),
            'requestToken' => old('request_token', (string) Str::uuid()),
            'transactionDateContext' => $this->accountingPeriodService->transactionDateContext($company),
            'transactionTypeDefinition' => TransactionTypes::configuredDefinition(
                $category,
                $categoryMetadata,
                $categoryOption?->label,
            ),
            'dueSettlementContext' => $dueSettlementContext,
        ]);
    }

    public function preview(Request $request): JsonResponse
    {
        $companyId = (int) $request->user()->company_id;
        $category = strtoupper((string) $request->input('category'));

        $validated = $request->validate([
            'category' => [
                'required',
                Rule::exists('accounting_options', 'value')->where(fn ($query) => $query
                    ->where('option_group', AccountingOption::GROUP_TRANSACTION_CATEGORY)
                    ->where('is_active', true)),
            ],
            'settlement_type' => [
                'nullable',
                Rule::exists('accounting_options', 'value')->where(fn ($query) => $query
                    ->where('option_group', AccountingOption::GROUP_SETTLEMENT_TYPE)
                    ->where('is_active', true)),
            ],
            'transaction_head_id' => [
                'required', 'integer',
                Rule::exists('transaction_heads', 'id')->where(fn ($query) => $query
                    ->where('company_id', $companyId)
                    ->where('category', $category)
                    ->where('is_active', true)
                    ->whereNotNull('posting_account_id')),
            ],
            'money_account_id' => [
                'nullable', 'integer',
                Rule::exists('money_accounts', 'id')->where(fn ($query) => $query
                    ->where('company_id', $companyId)
                    ->where('is_active', true)
                    ->whereNotNull('chart_of_account_id')),
            ],
            'party_id' => [
                'nullable', 'integer',
                Rule::exists('parties', 'id')->where(fn ($query) => $query
                    ->where('company_id', $companyId)
                    ->where('is_active', true)),
            ],
            'amount' => ['nullable', 'numeric', 'min:0'],
            'paid_amount' => ['nullable', 'numeric', 'min:0'],
        ]);

        $head = TransactionHead::query()
            ->with('postingAccount')
            ->where('company_id', $companyId)
            ->where('category', $category)
            ->where('is_active', true)
            ->whereNotNull('posting_account_id')
            ->whereHas('postingAccount', fn ($query) => $query
                ->where('company_id', $companyId)
                ->where('is_active', true))
            ->findOrFail($validated['transaction_head_id']);

        $moneyAccount = filled($validated['money_account_id'] ?? null)
            ? MoneyAccount::query()
                ->with('chartOfAccount')
                ->where('company_id', $companyId)
                ->where('is_active', true)
                ->whereHas('chartOfAccount', fn ($query) => $query
                    ->where('company_id', $companyId)
                    ->where('is_active', true))
                ->find($validated['money_account_id'])
            : null;

        $party = null;

        $scale = \App\Support\CompanyContext::decimalPlaces();
        $amount = $this->decimalAmount->normalize($validated['amount'] ?? 0, $scale);
        $settlement = [
            'settlement_type' => TransactionTypes::CASH,
            'paid_amount' => '0.00',
            'due_amount' => '0.00',
            'due_date' => null,
        ];
        $lines = [];
        $previewError = null;
        $rule = null;
        $requiresMoney = false;
        $requiresParty = false;
        $expectedPartyType = $head->party_type ?: TransactionTypes::partyType($category);

        try {
            $settlement = $this->settlementService->prepare($amount, $validated, $scale);
            $settlementType = $settlement['settlement_type'];

            if (! $head->allowsSettlement($settlementType)) {
                throw ValidationException::withMessages([
                    'paid_amount' => 'The amount entered creates a payment type that is not allowed for this transaction head.',
                ]);
            }

            $rule = $this->ruleMatcher->match($companyId, $category, $settlementType);
            $requiresMoney = $this->settlementService->requiresMoney($rule);
            $requiresParty = $this->settlementService->requiresParty($rule);
            $expectedPartyType = $this->partyResolver->expectedPartyType($head, $rule);
            $party = $requiresParty
                ? $this->partyResolver->resolveRequired(
                    $companyId,
                    $head,
                    $rule,
                    $validated['party_id'] ?? null,
                )
                : null;

            if ($requiresMoney && ! $moneyAccount) {
                throw ValidationException::withMessages([
                    'money_account_id' => TransactionTypes::moneyLabel($category).' is required.',
                ]);
            }

            $lines = $this->journalBuilder->buildFromRule(
                $head,
                $moneyAccount,
                $party,
                $amount,
                $settlement['paid_amount'],
                $settlement['due_amount'],
                $rule,
            );
        } catch (ValidationException $exception) {
            $previewError = collect($exception->errors())->flatten()->first();
        }

        $settlementType = $settlement['settlement_type'];
        $html = view('transactions.partials.preview', [
            'head' => $head,
            'rule' => $rule,
            'lines' => $lines,
            'amount' => $amount,
            'settlement' => $settlement,
            'previewError' => $previewError,
            'sourceLabels' => $this->optionService->labels(AccountingOption::GROUP_ACCOUNTING_SOURCE),
            'partyTypeLabels' => $this->optionService->labels(AccountingOption::GROUP_RULE_PARTY_TYPE),
            'settlementLabels' => $this->optionService->labels(AccountingOption::GROUP_SETTLEMENT_TYPE),
            'transactionTypeLabel' => $this->optionService->labels(AccountingOption::GROUP_TRANSACTION_CATEGORY)[$category] ?? $category,
        ])->render();

        return response()->json([
            'html' => $html,
            'settlementType' => $settlementType,
            'moneyRequired' => $requiresMoney,
            'partyRequired' => $requiresParty,
            'splitRequired' => $settlementType === TransactionTypes::PARTIAL,
            'dueRequired' => in_array($settlementType, [TransactionTypes::CREDIT, TransactionTypes::PARTIAL], true),
            'partyType' => $expectedPartyType,
            'autoSelectedPartyId' => $requiresParty && $party ? $party->id : null,
            'autoSelectedPartyLabel' => $requiresParty && $party ? $party->code.' — '.$party->name : null,
            'allowedSettlements' => $head->allowedSettlementCodes(),
            'moneyLabel' => TransactionTypes::moneyLabel($category),
            'partyLabel' => TransactionTypes::partyLabel($category),
        ]);
    }

    public function store(StoreTransactionRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        if ((bool) ($validated['due_settlement'] ?? false)) {
            $this->validateDueSettlementPosting($validated, (int) $request->user()->company_id);
            $validated['settlement_type'] = TransactionTypes::CASH;
            $validated['paid_amount'] = $validated['amount'];
        }

        $transaction = $this->transactionPostingService->post($validated, $request->user());
        $this->transactionAttachmentService->storeUploaded(
            $transaction,
            $request->file('transaction_attachments'),
            $request->user(),
        );

        $transaction->loadMissing('salesInvoice');

        $message = 'Transaction '.$transaction->voucher_no.' posted successfully.';
        if ($transaction->salesInvoice) {
            $message .= ' Sales invoice '.$transaction->salesInvoice->invoice_no.' generated and download started.';
        }

        if ($request->user()->canAccounting('transactions.view')) {
            $redirect = redirect()->route('transactions.index')->with('success', $message);

            if ($transaction->salesInvoice) {
                $redirect
                    ->with('invoice_download_url', route('sales-invoices.download', $transaction->salesInvoice))
                    ->with('invoice_show_url', route('sales-invoices.show', $transaction->salesInvoice));
            }

            return $redirect;
        }

        return redirect()
            ->route('transactions.create', ['category' => $transaction->category])
            ->with('success', $message)
            ->with('warning', 'The transaction was saved, but your role is not allowed to view the register.');
    }

    /** @return array<string, mixed> */
    private function dueSettlementContext(Request $request, int $companyId): array
    {
        $empty = [
            'active' => false,
            'category' => null,
            'due_type' => null,
            'party_id' => null,
            'party_label' => null,
            'account_id' => null,
            'account_label' => null,
            'transaction_head_id' => null,
            'amount' => null,
            'as_of_date' => null,
            'message' => null,
        ];

        if (! $request->boolean('due_settlement')) {
            return $empty;
        }

        $dueType = $this->normalizeDueType($request->query('due_type'));
        $partyId = (int) $request->query('party_id');
        $accountId = (int) $request->query('account_id');
        $asOfDate = filled($request->query('as_of_date'))
            ? (string) $request->query('as_of_date')
            : now()->toDateString();

        if (! $dueType || $partyId <= 0 || $accountId <= 0) {
            return array_replace($empty, [
                'active' => true,
                'message' => 'The due settlement link is incomplete. Select the party and due again from Due Management.',
            ]);
        }

        $category = $dueType === 'Receivable'
            ? TransactionTypes::CUSTOMER_COLLECTION
            : TransactionTypes::SUPPLIER_PAYMENT;

        $party = Party::query()
            ->with(['receivableAccount', 'payableAccount'])
            ->where('company_id', $companyId)
            ->where('is_active', true)
            ->find($partyId);

        $account = ChartOfAccount::query()
            ->where('company_id', $companyId)
            ->where('level', 3)
            ->where('is_active', true)
            ->find($accountId);

        if (! $party || ! $account) {
            return array_replace($empty, [
                'active' => true,
                'category' => $category,
                'message' => 'The selected party or due ledger is no longer available.',
            ]);
        }

        $mappedAccountId = $dueType === 'Receivable'
            ? $party->receivable_account_id
            : $party->payable_account_id;

        if ((int) $mappedAccountId !== (int) $account->id) {
            return array_replace($empty, [
                'active' => true,
                'category' => $category,
                'message' => 'The selected party is not mapped to this due ledger anymore.',
            ]);
        }

        $currentDue = $this->currentDueBalance($companyId, (int) $party->id, (int) $account->id, $dueType, $asOfDate);
        $head = TransactionHead::query()
            ->where('company_id', $companyId)
            ->where('category', $category)
            ->where('is_active', true)
            ->whereNotNull('posting_account_id')
            ->orderByRaw('CASE WHEN posting_account_id = ? THEN 0 ELSE 1 END', [$account->id])
            ->orderBy('id')
            ->first();

        return [
            'active' => true,
            'category' => $category,
            'due_type' => $dueType,
            'party_id' => (int) $party->id,
            'party_label' => $party->code.' — '.$party->name,
            'account_id' => (int) $account->id,
            'account_label' => $account->code.' — '.$account->name,
            'transaction_head_id' => $head?->id,
            'amount' => number_format(max($currentDue, 0), \App\Support\CompanyContext::decimalPlaces(), '.', ''),
            'as_of_date' => $asOfDate,
            'message' => $currentDue > 0
                ? null
                : 'This party has no outstanding due for the selected account.',
        ];
    }

    /** @param array<string, mixed> $data */
    private function validateDueSettlementPosting(array $data, int $companyId): void
    {
        $dueType = $this->normalizeDueType($data['due_type'] ?? null);

        if (! $dueType) {
            throw ValidationException::withMessages(['amount' => 'Invalid due settlement type.']);
        }

        $expectedCategory = $dueType === 'Receivable'
            ? TransactionTypes::CUSTOMER_COLLECTION
            : TransactionTypes::SUPPLIER_PAYMENT;

        if (($data['category'] ?? null) !== $expectedCategory) {
            throw ValidationException::withMessages(['category' => 'The selected transaction type does not match this due settlement.']);
        }

        if ((int) ($data['party_id'] ?? 0) !== (int) ($data['due_party_id'] ?? 0)) {
            throw ValidationException::withMessages(['party_id' => 'The selected party does not match this due settlement.']);
        }

        $party = Party::query()
            ->with(['receivableAccount', 'payableAccount'])
            ->where('company_id', $companyId)
            ->where('is_active', true)
            ->findOrFail((int) $data['due_party_id']);

        $account = ChartOfAccount::query()
            ->where('company_id', $companyId)
            ->where('level', 3)
            ->where('is_active', true)
            ->findOrFail((int) $data['due_account_id']);

        $mappedAccountId = $dueType === 'Receivable'
            ? $party->receivable_account_id
            : $party->payable_account_id;

        if ((int) $mappedAccountId !== (int) $account->id) {
            throw ValidationException::withMessages(['party_id' => 'The selected party is not mapped to this due ledger anymore.']);
        }

        $head = TransactionHead::query()
            ->where('company_id', $companyId)
            ->where('category', $expectedCategory)
            ->where('is_active', true)
            ->findOrFail((int) $data['transaction_head_id']);

        if ((int) $head->posting_account_id !== (int) $account->id) {
            throw ValidationException::withMessages(['transaction_head_id' => 'The selected transaction head is not linked to this due ledger.']);
        }

        $currentDue = $this->currentDueBalance(
            $companyId,
            (int) $party->id,
            (int) $account->id,
            $dueType,
            (string) ($data['due_as_of_date'] ?? now()->toDateString()),
        );

        $amount = round((float) ($data['amount'] ?? 0), 2);

        if ($currentDue <= 0) {
            throw ValidationException::withMessages(['amount' => 'This party has no outstanding due for the selected account.']);
        }

        if ($amount > $currentDue) {
            throw ValidationException::withMessages(['amount' => 'The settlement amount cannot be greater than the current due balance.']);
        }
    }

    private function currentDueBalance(int $companyId, int $partyId, int $accountId, string $dueType, string $asOfDate): float
    {
        $report = $this->reportService->dueReport($companyId, [
            'as_of_date' => $asOfDate,
            'due_type' => strtolower($dueType),
            'include_zero_balances' => true,
        ]);

        $row = collect($report['rows'])->first(fn (array $item): bool =>
            (int) $item['party_id'] === $partyId
            && (int) $item['account_id'] === $accountId
            && $item['due_type'] === $dueType
        );

        return round((float) ($row['closing_balance'] ?? 0), 2);
    }

    private function normalizeDueType(mixed $value): ?string
    {
        return match (strtolower(trim((string) $value))) {
            'receivable' => 'Receivable',
            'payable' => 'Payable',
            default => null,
        };
    }

}
