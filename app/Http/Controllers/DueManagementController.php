<?php

namespace App\Http\Controllers;

use App\Models\CashBankAccount;
use App\Models\DueRegister;
use App\Models\LedgerMappingRule;
use App\Models\VoucherHeader;
use App\Services\Accounting\TransactionPostingService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Throwable;

class DueManagementController extends Controller
{
    public function index(Request $request): View
    {
        $rows = $this->buildDueRows();

        $filteredRows = $this->filterRows($rows, $request);

        return view('due-management.index', [
            'dueRows' => $filteredRows,
            'allDueRows' => $rows,
            'stats' => $this->stats($rows),
            'cashBankAccounts' => CashBankAccount::query()
                ->where('status', 'Active')
                ->with('linkedLedger.accountType')
                ->orderBy('cash_bank_name')
                ->get(),
            'settlementRules' => $this->settlementRules(),
            'filters' => [
                'search' => (string) $request->query('search', ''),
                'due_type' => (string) $request->query('due_type', 'All'),
                'status' => (string) $request->query('status', 'All'),
                'from_date' => (string) $request->query('from_date', ''),
                'to_date' => (string) $request->query('to_date', ''),
            ],
        ]);
    }

    public function settle(Request $request, TransactionPostingService $postingService): JsonResponse
    {
        $validated = $request->validate([
            'party_id' => ['required', 'integer', Rule::exists('parties', 'id')->where(fn ($query) => $query->where('status', 'Active'))],
            'account_id' => ['required', 'integer', Rule::exists('chart_of_accounts', 'id')->where(fn ($query) => $query->where('status', 'Active'))],
            'due_type' => ['required', Rule::in(['Payable', 'Receivable'])],
            'transaction_head_id' => ['required', 'integer', Rule::exists('transaction_heads', 'id')->where(fn ($query) => $query->where('status', 'Active'))],
            'settlement_type_id' => ['required', 'integer', Rule::exists('settlement_types', 'id')->where(fn ($query) => $query->where('status', 'Active'))],
            'cash_bank_account_id' => ['required', 'integer', Rule::exists('cash_bank_accounts', 'id')->where(fn ($query) => $query->where('status', 'Active'))],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'voucher_date' => ['required', 'date'],
            'reference' => ['nullable', 'string', 'max:150'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $amount = round((float) $validated['amount'], 2);
        $balance = $this->currentBalance(
            partyId: (int) $validated['party_id'],
            accountId: (int) $validated['account_id'],
            dueType: (string) $validated['due_type']
        );

        if ($balance <= 0) {
            throw ValidationException::withMessages([
                'amount' => 'This due record has no outstanding balance.',
            ]);
        }

        if ($amount > $balance) {
            throw ValidationException::withMessages([
                'amount' => 'Payment / collection amount cannot be greater than the current due balance.',
            ]);
        }

        $expectedEffect = $validated['due_type'] === 'Payable'
            ? 'Decrease Liability'
            : 'Decrease Receivable';

        $rule = LedgerMappingRule::query()
            ->with(['transactionHead', 'settlementType', 'debitAccount.accountType', 'creditAccount.accountType'])
            ->where('transaction_head_id', $validated['transaction_head_id'])
            ->where('settlement_type_id', $validated['settlement_type_id'])
            ->where('status', 'Active')
            ->first();

        if (!$rule) {
            throw ValidationException::withMessages([
                'transaction_head_id' => 'No active ledger mapping rule exists for the selected Transaction Head and Settlement Type.',
            ]);
        }

        if ($rule->party_ledger_effect !== $expectedEffect) {
            throw ValidationException::withMessages([
                'transaction_head_id' => $validated['due_type'] === 'Payable'
                    ? 'Select a payment rule that debits Accounts Payable and credits Cash/Bank.'
                    : 'Select a collection rule that debits Cash/Bank and credits Accounts Receivable.',
            ]);
        }

        try {
            $postingInput = [
                'voucher_date' => $validated['voucher_date'],
                'transaction_head_id' => (int) $validated['transaction_head_id'],
                'settlement_type_id' => (int) $validated['settlement_type_id'],
                'party_id' => (int) $validated['party_id'],
                'cash_bank_account_id' => (int) $validated['cash_bank_account_id'],
                'amount' => $amount,
                'reference' => $validated['reference'] ?? null,
                'notes' => $validated['notes'] ?? null,
                'status' => VoucherHeader::STATUS_POSTED,
            ];

            $preview = $postingService->preview($postingInput, $request->user()?->id, false);
            $this->validatePreviewTouchesDueAccount($preview, (int) $validated['account_id'], (string) $validated['due_type']);

            $voucher = $postingService->save($postingInput, null, $request->user()?->id);

            return response()->json([
                'success' => true,
                'message' => 'Due payment / collection posted successfully. Income or expense was not recorded again.',
                'data' => [
                    'voucher_id' => $voucher->id,
                    'voucher_number' => $voucher->voucher_number,
                    'remaining_balance' => max(0, $balance - $amount),
                ],
                'redirect' => route('due-management.index'),
            ], 201);
        } catch (ValidationException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            Log::error('Due settlement posting failed.', [
                'message' => $exception->getMessage(),
                'file' => $exception->getFile(),
                'line' => $exception->getLine(),
                'user_id' => $request->user()?->id,
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Due settlement failed: ' . ($exception->getMessage() ?: 'Please check Financial Year, Voucher Numbering, Cash/Bank, Party, and Ledger Mapping setup.'),
            ], 500);
        }
    }

    private function buildDueRows(): Collection
    {
        $movements = DueRegister::query()
            ->with(['party.partyType', 'account.accountType', 'voucherHeader.transactionHead'])
            ->whereHas('voucherHeader', fn ($query) => $query->where('status', VoucherHeader::STATUS_POSTED))
            ->orderBy('party_id')
            ->orderBy('account_id')
            ->get();

        return $movements
            ->groupBy(fn (DueRegister $movement) => implode('|', [
                $movement->party_id,
                $movement->account_id,
                $movement->due_type,
            ]))
            ->map(function (Collection $group) {
                $first = $group->first();
                $increaseRows = $group->where('movement', 'Increase');
                $decreaseRows = $group->where('movement', 'Decrease');
                $original = round((float) $increaseRows->sum('amount'), 2);
                $settled = round((float) $decreaseRows->sum('amount'), 2);
                $balance = round($original - $settled, 2);

                if ($original <= 0) {
                    return null;
                }

                $dueDate = $increaseRows
                    ->map(fn (DueRegister $movement) => $movement->due_date ?: $movement->voucherHeader?->voucher_date)
                    ->filter()
                    ->sort()
                    ->first();

                $latest = $group
                    ->sortByDesc(fn (DueRegister $movement) => $movement->voucherHeader?->voucher_date?->timestamp ?? 0)
                    ->first();

                $status = $this->computedDueStatus($balance, $settled, $dueDate);

                return [
                    'key' => $first->party_id . '|' . $first->account_id . '|' . $first->due_type,
                    'party_id' => (int) $first->party_id,
                    'party_name' => $first->party?->party_name ?? 'Unknown Party',
                    'party_type' => $first->party?->partyType?->name,
                    'account_id' => (int) $first->account_id,
                    'account_name' => $first->account?->display_name ?? $first->account?->account_name ?? 'Unknown Account',
                    'account_type' => $first->account?->accountType?->name,
                    'due_type' => $first->due_type,
                    'original_amount' => $original,
                    'settled_amount' => $settled,
                    'balance_due' => max(0, $balance),
                    'raw_balance' => $balance,
                    'due_date' => $dueDate ? Carbon::parse($dueDate)->toDateString() : null,
                    'status' => $status,
                    'latest_voucher' => $latest?->voucherHeader?->voucher_number,
                    'latest_voucher_date' => $latest?->voucherHeader?->voucher_date?->toDateString(),
                    'movement_count' => $group->count(),
                ];
            })
            ->filter()
            ->sortBy([
                ['due_type', 'asc'],
                ['due_date', 'asc'],
                ['party_name', 'asc'],
            ])
            ->values();
    }

    private function filterRows(Collection $rows, Request $request): Collection
    {
        $search = trim((string) $request->query('search', ''));
        $dueType = (string) $request->query('due_type', 'All');
        $status = (string) $request->query('status', 'All');
        $fromDate = (string) $request->query('from_date', '');
        $toDate = (string) $request->query('to_date', '');

        return $rows->filter(function (array $row) use ($search, $dueType, $status, $fromDate, $toDate) {
            if ($dueType !== 'All' && $row['due_type'] !== $dueType) {
                return false;
            }

            if ($status !== 'All' && $row['status'] !== $status) {
                return false;
            }

            if ($fromDate !== '' && $row['due_date'] && $row['due_date'] < $fromDate) {
                return false;
            }

            if ($toDate !== '' && $row['due_date'] && $row['due_date'] > $toDate) {
                return false;
            }

            if ($search === '') {
                return true;
            }

            $haystack = strtolower(implode(' ', array_filter([
                $row['party_name'],
                $row['party_type'],
                $row['account_name'],
                $row['due_type'],
                $row['status'],
                $row['latest_voucher'],
            ])));

            return str_contains($haystack, strtolower($search));
        })->values();
    }

    private function stats(Collection $rows): array
    {
        $openRows = $rows->filter(fn (array $row) => $row['balance_due'] > 0);

        return [
            'payable' => round((float) $openRows->where('due_type', 'Payable')->sum('balance_due'), 2),
            'receivable' => round((float) $openRows->where('due_type', 'Receivable')->sum('balance_due'), 2),
            'overdue' => $openRows->where('status', 'Overdue')->count(),
            'partial' => $openRows->where('status', 'Partially Paid')->count(),
        ];
    }

    private function currentBalance(int $partyId, int $accountId, string $dueType): float
    {
        return round((float) DueRegister::query()
            ->where('party_id', $partyId)
            ->where('account_id', $accountId)
            ->where('due_type', $dueType)
            ->whereHas('voucherHeader', fn ($query) => $query->where('status', VoucherHeader::STATUS_POSTED))
            ->sum('balance_effect'), 2);
    }

    private function computedDueStatus(float $balance, float $settled, mixed $dueDate): string
    {
        if ($balance <= 0) {
            return 'Paid / Collected';
        }

        if ($dueDate && Carbon::parse($dueDate)->lt(today())) {
            return 'Overdue';
        }

        if ($settled > 0) {
            return 'Partially Paid';
        }

        return 'Open';
    }

    private function settlementRules(): Collection
    {
        return LedgerMappingRule::query()
            ->with(['transactionHead', 'settlementType', 'debitAccount', 'creditAccount'])
            ->where('status', 'Active')
            ->whereIn('party_ledger_effect', ['Decrease Liability', 'Decrease Receivable'])
            ->orderBy('party_ledger_effect')
            ->get()
            ->map(fn (LedgerMappingRule $rule) => [
                'transaction_head_id' => $rule->transaction_head_id,
                'settlement_type_id' => $rule->settlement_type_id,
                'due_type' => $rule->party_ledger_effect === 'Decrease Liability' ? 'Payable' : 'Receivable',
                'label' => trim(($rule->transactionHead?->name ?? 'Transaction Head') . ' - ' . ($rule->settlementType?->name ?? 'Settlement')),
                'effect' => $rule->party_ledger_effect,
                'debit_account' => $rule->debitAccount?->account_name,
                'credit_account' => $rule->creditAccount?->account_name,
            ])
            ->values();
    }

    private function validatePreviewTouchesDueAccount(array $preview, int $accountId, string $dueType): void
    {
        $entries = collect($preview['entries'] ?? []);
        $expectedEntryType = $dueType === 'Payable' ? 'Debit' : 'Credit';

        $matched = $entries->contains(fn (array $entry) =>
            (int) ($entry['account_id'] ?? 0) === $accountId
            && ($entry['entry_type'] ?? null) === $expectedEntryType
        );

        if (!$matched) {
            throw ValidationException::withMessages([
                'transaction_head_id' => $dueType === 'Payable'
                    ? 'The selected rule must debit the same Accounts Payable ledger used by this due balance.'
                    : 'The selected rule must credit the same Accounts Receivable ledger used by this due balance.',
            ]);
        }
    }
}
