<?php

namespace App\Http\Controllers\Accounting;

use App\Http\Controllers\Controller;
use App\Http\Requests\Accounting\StoreTransactionRequest;
use App\Models\AccountingOption;
use App\Models\MoneyAccount;
use App\Models\Party;
use App\Models\Transaction;
use App\Models\TransactionHead;
use App\Services\Accounting\AccountingOptionService;
use App\Services\Accounting\DecimalAmount;
use App\Services\Accounting\JournalBuilder;
use App\Services\Accounting\TransactionEntryOptionService;
use App\Services\Accounting\TransactionPostingService;
use App\Services\Accounting\TransactionSettlementService;
use App\Services\Accounting\TransactionAttachmentService;
use App\Services\Company\CompanyAccountingPeriodService;
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
        private readonly TransactionAttachmentService $transactionAttachmentService,
        private readonly DecimalAmount $decimalAmount,
        private readonly AccountingOptionService $optionService,
        private readonly TransactionEntryOptionService $entryOptionService,
        private readonly CompanyAccountingPeriodService $accountingPeriodService,
    ) {}

    public function create(Request $request): View
    {
        $transactionCategories = $this->optionService->forGroup(AccountingOption::GROUP_TRANSACTION_CATEGORY);
        $requestedCategory = $request->string('category')->toString();
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
        ]);
    }

    public function preview(Request $request): JsonResponse
    {
        $companyId = $request->user()->company_id;

        $validated = $request->validate([
            'category' => [
                'required',
                Rule::exists('accounting_options', 'value')->where(fn ($query) => $query
                    ->where('option_group', AccountingOption::GROUP_TRANSACTION_CATEGORY)
                    ->where('is_active', true)),
            ],
            'transaction_head_id' => [
                'required',
                'integer',
                Rule::exists('transaction_heads', 'id')->where(fn ($query) => $query
                    ->where('company_id', $companyId)
                    ->where('category', (string) $request->input('category'))
                    ->where('is_active', true)
                    ->whereNotNull('accounting_rule_id')
                    ->whereNotNull('posting_account_id')),
            ],
            'money_account_id' => [
                'nullable',
                'integer',
                Rule::exists('money_accounts', 'id')->where(fn ($query) => $query
                    ->where('company_id', $companyId)
                    ->where('is_active', true)
                    ->whereNotNull('chart_of_account_id')),
            ],
            'party_id' => [
                'nullable',
                'integer',
                Rule::exists('parties', 'id')->where(fn ($query) => $query
                    ->where('company_id', $companyId)
                    ->where('is_active', true)),
            ],
            'amount' => ['nullable', 'numeric', 'min:0'],
            'paid_amount' => ['nullable', 'numeric', 'min:0'],
        ]);

        $head = TransactionHead::query()
            ->with(['accountingRule.lines', 'postingAccount'])
            ->where('company_id', $companyId)
            ->where('category', $validated['category'])
            ->where('is_active', true)
            ->whereNotNull('accounting_rule_id')
            ->whereNotNull('posting_account_id')
            ->whereHas('accountingRule', fn ($query) => $query
                ->where('company_id', $companyId)
                ->where('category', $validated['category'])
                ->where('is_active', true))
            ->whereHas('postingAccount', fn ($query) => $query
                ->where('company_id', $companyId)
                ->where('is_active', true))
            ->findOrFail($validated['transaction_head_id']);

        $moneyAccount = filled($validated['money_account_id'] ?? null)
            ? MoneyAccount::query()
                ->with('chartOfAccount')
                ->where('company_id', $companyId)
                ->where('is_active', true)
                ->whereNotNull('chart_of_account_id')
                ->whereHas('chartOfAccount', fn ($query) => $query
                    ->where('company_id', $companyId)
                    ->where('is_active', true))
                ->find($validated['money_account_id'])
            : null;

        $party = filled($validated['party_id'] ?? null)
            ? Party::query()
                ->with(['receivableAccount', 'payableAccount'])
                ->where('company_id', $companyId)
                ->where('is_active', true)
                ->find($validated['party_id'])
            : null;

        $scale = \App\Support\CompanyContext::decimalPlaces();
        $amount = $this->decimalAmount->normalize($validated['amount'] ?? 0, $scale);
        $settlement = ['settlement_type' => Transaction::SETTLEMENT_NORMAL, 'paid_amount' => null, 'due_amount' => null, 'due_date' => null];
        $lines = [];
        $previewError = null;

        $partyTypeLabels = $this->optionService->labels(AccountingOption::GROUP_RULE_PARTY_TYPE);

        try {
            $rule = $head->accountingRule;
            $requiresSplitAmounts = $this->settlementService->requiresSplitAmounts($rule);
            $requiresMoney = $this->settlementService->requiresMoney($rule);
            $requiresParty = $this->settlementService->requiresParty($rule);
            $settlement = $this->settlementService->prepare($amount, $validated, $scale, $requiresSplitAmounts);

            if ($requiresMoney && ! $moneyAccount) {
                throw ValidationException::withMessages([
                    'money_account_id' => 'A money account is required because the selected accounting rule has a Selected Money posting line.',
                ]);
            }

            if ($requiresParty && ! $party) {
                throw ValidationException::withMessages([
                    'party_id' => 'A party is required because the selected accounting rule has a party receivable/payable posting line.',
                ]);
            }

            if ($requiresParty && $rule->party_type !== 'Any' && $party?->type !== $rule->party_type) {
                throw ValidationException::withMessages(['party_id' => 'This transaction requires a '.($partyTypeLabels[$rule->party_type] ?? $rule->party_type).' party.']);
            }

            $lines = $this->journalBuilder->buildFromRule(
                $head,
                $moneyAccount,
                $party,
                $amount,
                $settlement['paid_amount'],
                $settlement['due_amount'],
            );
        } catch (ValidationException $exception) {
            $previewError = collect($exception->errors())->flatten()->first();
        }

        $html = view('transactions.partials.preview', [
            'head' => $head,
            'rule' => $head->accountingRule,
            'lines' => $lines,
            'amount' => $amount,
            'settlement' => $settlement,
            'previewError' => $previewError,
            'sourceLabels' => $this->optionService->labels(AccountingOption::GROUP_ACCOUNTING_SOURCE),
            'partyTypeLabels' => $partyTypeLabels,
        ])->render();

        $rule = $head->accountingRule;

        return response()->json([
            'html' => $html,
            'moneyRequired' => $this->settlementService->requiresMoney($rule),
            'partyRequired' => $this->settlementService->requiresParty($rule),
            'splitRequired' => $this->settlementService->requiresSplitAmounts($rule),
            'partyType' => $rule->party_type,
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
            ->with('warning', 'The transaction was saved, but your role is not allowed to view the register. You have been returned to Transaction Entry.');
    }

}
