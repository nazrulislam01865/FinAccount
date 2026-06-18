<?php

namespace App\Http\Controllers\Accounting;

use App\Http\Controllers\Controller;
use App\Models\AccountingRule;
use App\Models\ChartOfAccount;
use App\Models\MoneyAccount;
use App\Models\Party;
use App\Models\TransactionHead;
use App\Services\Accounting\JournalBuilder;
use App\Services\Accounting\Reports\FinancialReportService;
use App\Services\Accounting\TransactionPostingService;
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
        private readonly JournalBuilder $journalBuilder,
        private readonly TransactionPostingService $transactionPostingService,
    ) {}

    public function index(Request $request): View
    {
        $companyId = (int) $request->user()->company_id;
        $filters = collect(['as_of_date', 'search', 'due_type', 'include_zero_balances'])
            ->mapWithKeys(fn (string $key): array => [$key => $request->query($key)])
            ->filter(fn ($value): bool => $value !== null && $value !== '')
            ->all();

        $report = $this->reportService->dueReport($companyId, $filters);

        return view('reports.due-management', [
            'report' => $report,
            'settlementHeads' => TransactionHead::query()
                ->with(['accountingRule', 'postingAccount'])
                ->where('company_id', $companyId)
                ->where('is_active', true)
                ->whereNotNull('accounting_rule_id')
                ->whereNotNull('posting_account_id')
                ->whereHas('accountingRule', fn ($query) => $query
                    ->where('company_id', $companyId)
                    ->where('is_active', true)
                    ->where('money_required', true)
                    ->where('party_required', true))
                ->orderBy('category')
                ->orderBy('name')
                ->get(),
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
                    ->where('is_active', true)),
            ],
            'due_type' => ['required', Rule::in(['Receivable', 'Payable'])],
            'transaction_date' => ['required', 'date'],
            'transaction_head_id' => [
                'required', 'integer',
                Rule::exists('transaction_heads', 'id')->where(fn ($query) => $query
                    ->where('company_id', $companyId)
                    ->where('is_active', true)
                    ->whereNotNull('accounting_rule_id')
                    ->whereNotNull('posting_account_id')),
            ],
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

        $party = Party::query()
            ->with(['receivableAccount', 'payableAccount'])
            ->where('company_id', $companyId)
            ->where('is_active', true)
            ->findOrFail($validated['party_id']);

        $account = ChartOfAccount::query()
            ->where('company_id', $companyId)
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
                'amount' => 'Settlement amount cannot be greater than the current due balance.',
            ]);
        }

        $head = TransactionHead::query()
            ->with(['accountingRule', 'postingAccount'])
            ->where('company_id', $companyId)
            ->where('is_active', true)
            ->findOrFail($validated['transaction_head_id']);

        $moneyAccount = MoneyAccount::query()
            ->with('chartOfAccount')
            ->where('company_id', $companyId)
            ->where('is_active', true)
            ->findOrFail($validated['money_account_id']);

        $this->validateSettlementRule($head, $moneyAccount, $party, $account, $dueType, $amount);

        $description = filled($validated['description'] ?? null)
            ? trim((string) $validated['description'])
            : 'Due settlement for '.$party->name;

        $transaction = $this->transactionPostingService->post([
            'category' => $head->category,
            'transaction_date' => $validated['transaction_date'],
            'transaction_head_id' => $head->id,
            'money_account_id' => $moneyAccount->id,
            'party_id' => $party->id,
            'amount' => number_format($amount, 2, '.', ''),
            'reference' => filled($validated['reference'] ?? null) ? trim((string) $validated['reference']) : null,
            'description' => $description,
            'request_token' => (string) Str::uuid(),
        ], $request->user());

        return redirect()
            ->route('reports.due-management', $request->only(['as_of_date', 'search', 'due_type']))
            ->with('success', 'Due settlement posted successfully as voucher '.$transaction->voucher_no.'.');
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

    private function validateSettlementRule(
        TransactionHead $head,
        MoneyAccount $moneyAccount,
        Party $party,
        ChartOfAccount $dueAccount,
        string $dueType,
        float $amount,
    ): void {
        $rule = $head->accountingRule;

        if (! $rule || ! $rule->is_active || ! $rule->money_required || ! $rule->party_required) {
            throw ValidationException::withMessages([
                'transaction_head_id' => 'Select an active transaction head whose accounting rule requires both party and money account.',
            ]);
        }

        $lines = collect($this->journalBuilder->build(
            $head,
            $moneyAccount,
            $party,
            number_format($amount, 2, '.', ''),
        ));

        $dueLineIsValid = $lines->contains(function (array $line) use ($dueAccount, $dueType): bool {
            if ((int) $line['account']->id !== (int) $dueAccount->id) {
                return false;
            }

            return $dueType === 'Receivable'
                ? (float) $line['credit'] > 0
                : (float) $line['debit'] > 0;
        });

        $moneyLineIsValid = $lines->contains(function (array $line) use ($moneyAccount, $dueType): bool {
            if ((int) $line['account']->id !== (int) $moneyAccount->chart_of_account_id) {
                return false;
            }

            return $dueType === 'Receivable'
                ? (float) $line['debit'] > 0
                : (float) $line['credit'] > 0;
        });

        if (! $dueLineIsValid || ! $moneyLineIsValid) {
            throw ValidationException::withMessages([
                'transaction_head_id' => $dueType === 'Receivable'
                    ? 'Select a collection rule that debits the selected money account and credits the party receivable account.'
                    : 'Select a payment rule that debits the party payable account and credits the selected money account.',
            ]);
        }
    }
}
