<?php

namespace App\Http\Controllers\Accounting;

use App\Http\Controllers\Controller;
use App\Http\Requests\Accounting\StoreTransactionRequest;
use App\Models\AccountingOption;
use App\Models\MoneyAccount;
use App\Models\Transaction;
use App\Models\TransactionHead;
use App\Services\Accounting\AccountingOptionService;
use App\Services\Accounting\DecimalAmount;
use App\Services\Accounting\JournalBuilder;
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
    ) {}

    public function create(Request $request): View
    {
        $transactionCategories = $this->optionService->forGroup(AccountingOption::GROUP_TRANSACTION_CATEGORY);
        $requestedCategory = strtoupper($request->string('category')->toString());
        $category = $transactionCategories->contains('value', $requestedCategory)
            ? $requestedCategory
            : ($transactionCategories->first()?->value ?? '');
        $company = $request->user()->company;
        abort_unless($company, 404);
        $companyId = $company->id;

        return view('transactions.create', [
            'category' => $category,
            'categoryOption' => $transactionCategories->firstWhere('value', $category),
            'transactionCategories' => $transactionCategories,
            'transactionHeads' => $this->entryOptionService->transactionHeads($companyId, $category),
            'moneyAccounts' => $this->entryOptionService->moneyAccounts($companyId),
            'moneyKindLabels' => $this->optionService->labels(AccountingOption::GROUP_MONEY_ACCOUNT_KIND),
            'parties' => $this->entryOptionService->parties($companyId),
            'partyTypeLabels' => $this->optionService->labels(AccountingOption::GROUP_PARTY_TYPE),
            'requestToken' => old('request_token', (string) Str::uuid()),
            'transactionDateContext' => $this->accountingPeriodService->transactionDateContext($company),
            'transactionTypeDefinition' => TransactionTypes::definition($category),
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
        $transaction = $this->transactionPostingService->post($request->validated(), $request->user());
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
}
