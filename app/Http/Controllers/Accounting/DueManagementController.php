<?php

namespace App\Http\Controllers\Accounting;

use App\Http\Controllers\Controller;
use App\Models\ChartOfAccount;
use App\Models\MoneyAccount;
use App\Models\Party;
use App\Models\TransactionHead;
use App\Services\Accounting\Reports\FinancialReportService;
use App\Services\Accounting\TransactionPostingService;
use App\Support\TransactionTypes;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class DueManagementController extends Controller
{
    public function __construct(
        private readonly FinancialReportService $reportService,
        private readonly TransactionPostingService $transactionPostingService,
    ) {}

    public function index(Request $request): View
    {
        $companyId = (int) $request->user()->company_id;
        $filters = collect(['as_of_date', 'search', 'due_type', 'include_zero_balances'])
            ->mapWithKeys(fn (string $key): array => [$key => $request->query($key)])
            ->filter(fn ($value): bool => $value !== null && $value !== '')
            ->all();

        return view('reports.due-management', [
            'report' => $this->reportService->dueReport($companyId, $filters),
            'moneyAccounts' => MoneyAccount::query()
                ->with('chartOfAccount')
                ->where('company_id', $companyId)
                ->where('is_active', true)
                ->whereNotNull('chart_of_account_id')
                ->whereHas('chartOfAccount', fn ($query) => $query
                    ->where('company_id', $companyId)
                    ->where('is_active', true))
                ->orderBy('name')
                ->get(),
        ]);
    }

    public function settle(Request $request): RedirectResponse
    {
        $companyId = (int) $request->user()->company_id;

        $validated = $request->validate([
            'as_of_date' => ['nullable', 'date'],
            'party_id' => [
                'required', 'integer',
                Rule::exists('parties', 'id')->where(fn ($query) => $query
                    ->where('company_id', $companyId)
                    ->where('is_active', true)),
            ],
            'account_id' => [
                'required', 'integer',
                Rule::exists('chart_of_accounts', 'id')->where(fn ($query) => $query
                    ->where('company_id', $companyId)
                    ->where('level', 3)
                    ->where('is_active', true)),
            ],
            'due_type' => ['required', Rule::in(['Receivable', 'Payable'])],
            'transaction_date' => ['required', 'date'],
            'money_account_id' => [
                'required', 'integer',
                Rule::exists('money_accounts', 'id')->where(fn ($query) => $query
                    ->where('company_id', $companyId)
                    ->where('is_active', true)
                    ->whereNotNull('chart_of_account_id')),
            ],
            'amount' => ['required', 'numeric', 'gt:0'],
            'reference' => ['nullable', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:1000'],
        ]);

        $asOfDate = $validated['as_of_date'] ?: $validated['transaction_date'];
        $dueType = (string) $validated['due_type'];
        $amount = round((float) $validated['amount'], 2);
        $transactionType = $dueType === 'Receivable'
            ? TransactionTypes::CUSTOMER_COLLECTION
            : TransactionTypes::SUPPLIER_PAYMENT;

        $party = Party::query()
            ->with(['receivableAccount', 'payableAccount'])
            ->where('company_id', $companyId)
            ->where('is_active', true)
            ->findOrFail($validated['party_id']);

        $account = ChartOfAccount::query()
            ->where('company_id', $companyId)
            ->where('level', 3)
            ->where('is_active', true)
            ->findOrFail($validated['account_id']);

        $this->validatePartyAccountMapping($party, $account, $dueType);

        $currentDue = $this->currentDueBalance(
            companyId: $companyId,
            partyId: (int) $party->id,
            accountId: (int) $account->id,
            dueType: $dueType,
            asOfDate: (string) $asOfDate,
        );

        if ($currentDue <= 0) {
            throw ValidationException::withMessages([
                'amount' => 'This party has no outstanding due for the selected account.',
            ]);
        }

        if ($amount > $currentDue) {
            throw ValidationException::withMessages([
                'amount' => 'The amount cannot be greater than the current due balance.',
            ]);
        }

        $head = TransactionHead::query()
            ->where('company_id', $companyId)
            ->where('category', $transactionType)
            ->where('is_active', true)
            ->whereNotNull('posting_account_id')
            ->orderByRaw('CASE WHEN posting_account_id = ? THEN 0 ELSE 1 END', [$account->id])
            ->orderBy('id')
            ->first();

        if (! $head) {
            throw ValidationException::withMessages([
                'amount' => $dueType === 'Receivable'
                    ? 'Customer Collection setup is missing. Create one active Customer Collection transaction head first.'
                    : 'Supplier Payment setup is missing. Create one active Supplier Payment transaction head first.',
            ]);
        }

        $description = filled($validated['description'] ?? null)
            ? trim((string) $validated['description'])
            : ($dueType === 'Receivable' ? 'Customer due collected from ' : 'Supplier due paid to ').$party->name;

        $transaction = $this->transactionPostingService->post([
            'category' => $transactionType,
            'settlement_type' => TransactionTypes::CASH,
            'transaction_date' => $validated['transaction_date'],
            'transaction_head_id' => $head->id,
            'money_account_id' => $validated['money_account_id'],
            'party_id' => $party->id,
            'amount' => number_format($amount, 2, '.', ''),
            'reference' => filled($validated['reference'] ?? null) ? trim((string) $validated['reference']) : null,
            'description' => $description,
            'request_token' => (string) Str::uuid(),
        ], $request->user());

        return redirect()
            ->route('reports.due-management', $request->only(['as_of_date', 'search', 'due_type']))
            ->with('success', ($dueType === 'Receivable' ? 'Customer payment collected' : 'Supplier payment posted').' as voucher '.$transaction->voucher_no.'.');
    }

    private function validatePartyAccountMapping(Party $party, ChartOfAccount $account, string $dueType): void
    {
        $mappedAccountId = $dueType === 'Receivable'
            ? $party->receivable_account_id
            : $party->payable_account_id;

        if ((int) $mappedAccountId !== (int) $account->id) {
            throw ValidationException::withMessages([
                'account_id' => 'The selected account is not mapped to this party for '.$dueType.' dues.',
            ]);
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
}
