<?php

namespace App\Http\Controllers\Accounting;

use App\Http\Controllers\Controller;
use App\Http\Requests\Accounting\StoreTransactionRequest;
use App\Models\AccountingOption;
use App\Models\MoneyAccount;
use App\Models\Party;
use App\Models\TransactionHead;
use App\Services\Accounting\AccountingOptionService;
use App\Services\Accounting\DecimalAmount;
use App\Services\Accounting\JournalBuilder;
use App\Services\Accounting\TransactionEntryOptionService;
use App\Services\Accounting\TransactionPostingService;
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
        private readonly DecimalAmount $decimalAmount,
        private readonly AccountingOptionService $optionService,
        private readonly TransactionEntryOptionService $entryOptionService,
    ) {}

    public function create(Request $request): View
    {
        $transactionCategories = $this->optionService->forGroup(AccountingOption::GROUP_TRANSACTION_CATEGORY);
        $requestedCategory = $request->string('category')->toString();
        $category = $transactionCategories->contains('value', $requestedCategory)
            ? $requestedCategory
            : ($transactionCategories->first()?->value ?? '');
        $companyId = $request->user()->company_id;

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
        ]);

        $head = TransactionHead::query()
            ->with(['accountingRule', 'postingAccount'])
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

        $amount = $this->decimalAmount->normalize($validated['amount'] ?? 0);
        $lines = [];
        $previewError = null;

        $partyTypeLabels = $this->optionService->labels(AccountingOption::GROUP_RULE_PARTY_TYPE);

        try {
            $rule = $head->accountingRule;

            if ($rule->money_required && ! $moneyAccount) {
                throw ValidationException::withMessages(['money_account_id' => 'A money account is required for this accounting rule.']);
            }

            if ($rule->party_required && ! $party) {
                throw ValidationException::withMessages(['party_id' => 'A party is required for this accounting rule.']);
            }

            if ($rule->party_required && $rule->party_type !== 'Any' && $party?->type !== $rule->party_type) {
                throw ValidationException::withMessages(['party_id' => 'This transaction requires a '.($partyTypeLabels[$rule->party_type] ?? $rule->party_type).' party.']);
            }

            $lines = $this->journalBuilder->build($head, $moneyAccount, $party, $amount);
        } catch (ValidationException $exception) {
            $previewError = collect($exception->errors())->flatten()->first();
        }

        $html = view('transactions.partials.preview', [
            'head' => $head,
            'rule' => $head->accountingRule,
            'lines' => $lines,
            'amount' => $amount,
            'previewError' => $previewError,
            'sourceLabels' => $this->optionService->labels(AccountingOption::GROUP_ACCOUNTING_SOURCE),
            'partyTypeLabels' => $partyTypeLabels,
        ])->render();

        return response()->json([
            'html' => $html,
            'moneyRequired' => $head->accountingRule->money_required,
            'partyRequired' => $head->accountingRule->party_required,
            'partyType' => $head->accountingRule->party_type,
        ]);
    }

    public function store(StoreTransactionRequest $request): RedirectResponse
    {
        $transaction = $this->transactionPostingService->post($request->validated(), $request->user());

        return redirect()
            ->route('transactions.index')
            ->with('success', 'Transaction '.$transaction->voucher_no.' posted successfully.');
    }

}
