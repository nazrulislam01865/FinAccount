<?php

namespace App\Http\Controllers\Accounting;

use App\Http\Controllers\Controller;
use App\Http\Requests\Accounting\StoreTransactionRequest;
use App\Models\MoneyAccount;
use App\Models\Party;
use App\Models\TransactionHead;
use App\Services\Accounting\DecimalAmount;
use App\Services\Accounting\JournalBuilder;
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
    ) {}

    public function create(Request $request): View
    {
        $category = in_array($request->string('category')->toString(), ['Sales', 'Payment', 'Liability'], true)
            ? $request->string('category')->toString()
            : 'Sales';

        $companyId = $request->user()->company_id;

        return view('transactions.create', [
            'category' => $category,
            'transactionHeads' => TransactionHead::query()
                ->with('accountingRule')
                ->where('company_id', $companyId)
                ->where('category', $category)
                ->where('is_active', true)
                ->whereHas('accountingRule', fn ($query) => $query->where('is_active', true))
                ->orderBy('name')
                ->get(),
            'moneyAccounts' => MoneyAccount::query()
                ->where('company_id', $companyId)
                ->where('is_active', true)
                ->orderBy('name')
                ->get(),
            'parties' => Party::query()
                ->where('company_id', $companyId)
                ->where('is_active', true)
                ->orderBy('name')
                ->get(),
            'requestToken' => old('request_token', (string) Str::uuid()),
        ]);
    }

    public function preview(Request $request): JsonResponse
    {
        $companyId = $request->user()->company_id;

        $validated = $request->validate([
            'transaction_head_id' => [
                'required',
                'integer',
                Rule::exists('transaction_heads', 'id')->where('company_id', $companyId),
            ],
            'money_account_id' => ['nullable', 'integer'],
            'party_id' => ['nullable', 'integer'],
            'amount' => ['nullable', 'numeric', 'min:0'],
        ]);

        $head = TransactionHead::query()
            ->with(['accountingRule', 'postingAccount'])
            ->where('company_id', $companyId)
            ->where('is_active', true)
            ->whereHas('accountingRule', fn ($query) => $query->where('is_active', true))
            ->findOrFail($validated['transaction_head_id']);

        $moneyAccount = filled($validated['money_account_id'] ?? null)
            ? MoneyAccount::query()
                ->with('chartOfAccount')
                ->where('company_id', $companyId)
                ->where('is_active', true)
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

        try {
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
        $transaction = $this->transactionPostingService->post(
            $request->validated(),
            $request->user(),
        );

        return redirect()
            ->route('transactions.index')
            ->with('success', 'Transaction '.$transaction->voucher_no.' posted successfully.');
    }
}
