<?php

namespace App\Http\Controllers;

use App\Models\AdvanceRegister;
use App\Models\CashBankAccount;
use App\Models\DueRegister;
use App\Models\LedgerMappingRule;
use App\Models\Party;
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

class AdvanceManagementController extends Controller
{
    public function index(Request $request): View
    {
        $rows = $this->buildAdvanceRows();
        $filteredRows = $this->filterRows($rows, $request);

        return view('advance-management.index', [
            'advanceRows' => $filteredRows,
            'allAdvanceRows' => $rows,
            'stats' => $this->stats($rows),
            'cashBankAccounts' => CashBankAccount::query()
                ->where('status', 'Active')
                ->with('linkedLedger.accountType')
                ->orderBy('cash_bank_name')
                ->get(),
            'parties' => Party::query()
                ->where('status', 'Active')
                ->with('partyType')
                ->orderBy('party_name')
                ->get(),
            'newAdvanceRules' => $this->rulePayload(['Increase Advance Asset', 'Increase Advance Liability']),
            'adjustmentRules' => $this->rulePayload(['Decrease Advance Asset', 'Decrease Advance Liability']),
            'filters' => [
                'search' => (string) $request->query('search', ''),
                'advance_type' => (string) $request->query('advance_type', 'All'),
                'status' => (string) $request->query('status', 'All'),
                'from_date' => (string) $request->query('from_date', ''),
                'to_date' => (string) $request->query('to_date', ''),
            ],
        ]);
    }

    public function store(Request $request, TransactionPostingService $postingService): JsonResponse
    {
        $validated = $request->validate([
            'entry_mode' => ['required', Rule::in(['New Advance', 'Advance Adjustment'])],
            'advance_type' => ['required', Rule::in(['Paid', 'Received'])],
            'party_id' => ['required', 'integer', Rule::exists('parties', 'id')->where(fn ($query) => $query->where('status', 'Active'))],
            'account_id' => ['nullable', 'integer', Rule::exists('chart_of_accounts', 'id')->where(fn ($query) => $query->where('status', 'Active'))],
            'transaction_head_id' => ['required', 'integer', Rule::exists('transaction_heads', 'id')->where(fn ($query) => $query->where('status', 'Active'))],
            'settlement_type_id' => ['required', 'integer', Rule::exists('settlement_types', 'id')->where(fn ($query) => $query->where('status', 'Active'))],
            'cash_bank_account_id' => ['nullable', 'integer', Rule::exists('cash_bank_accounts', 'id')->where(fn ($query) => $query->where('status', 'Active'))],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'voucher_date' => ['required', 'date'],
            'reference' => ['nullable', 'string', 'max:150'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $amount = round((float) $validated['amount'], 2);
        $entryMode = (string) $validated['entry_mode'];
        $advanceType = (string) $validated['advance_type'];
        $expectedEffect = $this->expectedEffect($entryMode, $advanceType);

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
                'transaction_head_id' => $this->effectErrorMessage($entryMode, $advanceType),
            ]);
        }

        if ($entryMode === 'New Advance' && !$validated['cash_bank_account_id']) {
            throw ValidationException::withMessages([
                'cash_bank_account_id' => 'Cash / Bank account is required when posting a new advance.',
            ]);
        }

        if ($entryMode === 'Advance Adjustment') {
            if (!$validated['account_id']) {
                throw ValidationException::withMessages([
                    'account_id' => 'Please select an open advance balance before posting an adjustment.',
                ]);
            }

            $balance = $this->currentAdvanceBalance(
                partyId: (int) $validated['party_id'],
                accountId: (int) $validated['account_id'],
                advanceType: $advanceType
            );

            if ($balance <= 0) {
                throw ValidationException::withMessages([
                    'amount' => 'This advance record has no outstanding balance.',
                ]);
            }

            if ($amount > $balance) {
                throw ValidationException::withMessages([
                    'amount' => 'Adjustment amount cannot be greater than the current advance balance.',
                ]);
            }
        }

        try {
            $postingInput = [
                'voucher_date' => $validated['voucher_date'],
                'transaction_head_id' => (int) $validated['transaction_head_id'],
                'settlement_type_id' => (int) $validated['settlement_type_id'],
                'party_id' => (int) $validated['party_id'],
                'cash_bank_account_id' => $validated['cash_bank_account_id'] ? (int) $validated['cash_bank_account_id'] : null,
                'amount' => $amount,
                'reference' => $validated['reference'] ?? null,
                'notes' => $validated['notes'] ?? null,
                'status' => VoucherHeader::STATUS_POSTED,
            ];

            $preview = $postingService->preview($postingInput, $request->user()?->id, false);

            if ($entryMode === 'Advance Adjustment') {
                $advanceAccountId = (int) $validated['account_id'];
                $this->validatePreviewTouchesAdvanceAccount($preview, $advanceAccountId, $advanceType);
                $this->validateLinkedDueBalance($preview, (int) $validated['party_id'], $advanceType, $amount);
            }

            $voucher = $postingService->save($postingInput, null, $request->user()?->id);

            return response()->json([
                'success' => true,
                'message' => $entryMode === 'New Advance'
                    ? 'Advance posted successfully.'
                    : 'Advance adjustment posted successfully. Related due balance was reduced where applicable.',
                'data' => [
                    'voucher_id' => $voucher->id,
                    'voucher_number' => $voucher->voucher_number,
                ],
                'redirect' => route('advance-management.index'),
            ], 201);
        } catch (ValidationException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            Log::error('Advance posting failed.', [
                'message' => $exception->getMessage(),
                'file' => $exception->getFile(),
                'line' => $exception->getLine(),
                'user_id' => $request->user()?->id,
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Advance posting failed: ' . ($exception->getMessage() ?: 'Please check Financial Year, Voucher Numbering, Cash/Bank, Party, Advance Mapping, and Ledger Mapping setup.'),
            ], 500);
        }
    }

    private function buildAdvanceRows(): Collection
    {
        $movements = AdvanceRegister::query()
            ->with(['party.partyType', 'account.accountType', 'voucherHeader.transactionHead'])
            ->whereHas('voucherHeader', fn ($query) => $query->where('status', VoucherHeader::STATUS_POSTED))
            ->orderBy('party_id')
            ->orderBy('account_id')
            ->get();

        return $movements
            ->groupBy(fn (AdvanceRegister $movement) => implode('|', [
                $movement->party_id,
                $movement->account_id,
                $movement->advance_type,
            ]))
            ->map(function (Collection $group) {
                $first = $group->first();
                $increaseRows = $group->where('movement', 'Increase');
                $decreaseRows = $group->where('movement', 'Decrease');
                $original = round((float) $increaseRows->sum('amount'), 2);
                $adjusted = round((float) $decreaseRows->sum('amount'), 2);
                $balance = round($original - $adjusted, 2);

                if ($original <= 0) {
                    return null;
                }

                $firstDate = $increaseRows
                    ->map(fn (AdvanceRegister $movement) => $movement->voucherHeader?->voucher_date)
                    ->filter()
                    ->sort()
                    ->first();

                $latest = $group
                    ->sortByDesc(fn (AdvanceRegister $movement) => $movement->voucherHeader?->voucher_date?->timestamp ?? 0)
                    ->first();

                $status = $this->computedAdvanceStatus($balance, $adjusted);

                return [
                    'key' => $first->party_id . '|' . $first->account_id . '|' . $first->advance_type,
                    'party_id' => (int) $first->party_id,
                    'party_name' => $first->party?->party_name ?? 'Unknown Party',
                    'party_type' => $first->party?->partyType?->name,
                    'account_id' => (int) $first->account_id,
                    'account_name' => $first->account?->display_name ?? $first->account?->account_name ?? 'Unknown Account',
                    'account_type' => $first->account?->accountType?->name,
                    'advance_type' => $first->advance_type,
                    'advance_type_label' => $first->advance_type === 'Paid' ? 'Advance Paid' : 'Advance Received',
                    'original_amount' => $original,
                    'adjusted_amount' => $adjusted,
                    'balance' => max(0, $balance),
                    'raw_balance' => $balance,
                    'advance_date' => $firstDate ? Carbon::parse($firstDate)->toDateString() : null,
                    'status' => $status,
                    'latest_voucher' => $latest?->voucherHeader?->voucher_number,
                    'latest_voucher_date' => $latest?->voucherHeader?->voucher_date?->toDateString(),
                    'movement_count' => $group->count(),
                ];
            })
            ->filter()
            ->sortBy([
                ['advance_type', 'asc'],
                ['advance_date', 'asc'],
                ['party_name', 'asc'],
            ])
            ->values();
    }

    private function filterRows(Collection $rows, Request $request): Collection
    {
        $search = trim((string) $request->query('search', ''));
        $advanceType = (string) $request->query('advance_type', 'All');
        $status = (string) $request->query('status', 'All');
        $fromDate = (string) $request->query('from_date', '');
        $toDate = (string) $request->query('to_date', '');

        return $rows->filter(function (array $row) use ($search, $advanceType, $status, $fromDate, $toDate) {
            if ($advanceType !== 'All' && $row['advance_type_label'] !== $advanceType) {
                return false;
            }

            if ($status !== 'All' && $row['status'] !== $status) {
                return false;
            }

            if ($fromDate !== '' && $row['advance_date'] && $row['advance_date'] < $fromDate) {
                return false;
            }

            if ($toDate !== '' && $row['advance_date'] && $row['advance_date'] > $toDate) {
                return false;
            }

            if ($search === '') {
                return true;
            }

            $haystack = strtolower(implode(' ', array_filter([
                $row['party_name'],
                $row['party_type'],
                $row['account_name'],
                $row['advance_type_label'],
                $row['status'],
                $row['latest_voucher'],
            ])));

            return str_contains($haystack, strtolower($search));
        })->values();
    }

    private function stats(Collection $rows): array
    {
        $openRows = $rows->filter(fn (array $row) => $row['balance'] > 0);

        return [
            'paid' => round((float) $openRows->where('advance_type', 'Paid')->sum('balance'), 2),
            'received' => round((float) $openRows->where('advance_type', 'Received')->sum('balance'), 2),
            'partial' => $openRows->where('status', 'Partially Adjusted')->count(),
            'closed' => $rows->where('status', 'Fully Adjusted')->count(),
        ];
    }

    private function currentAdvanceBalance(int $partyId, int $accountId, string $advanceType): float
    {
        return round((float) AdvanceRegister::query()
            ->where('party_id', $partyId)
            ->where('account_id', $accountId)
            ->where('advance_type', $advanceType)
            ->whereHas('voucherHeader', fn ($query) => $query->where('status', VoucherHeader::STATUS_POSTED))
            ->sum('balance_effect'), 2);
    }

    private function currentDueBalance(int $partyId, int $accountId, string $dueType): float
    {
        return round((float) DueRegister::query()
            ->where('party_id', $partyId)
            ->where('account_id', $accountId)
            ->where('due_type', $dueType)
            ->whereHas('voucherHeader', fn ($query) => $query->where('status', VoucherHeader::STATUS_POSTED))
            ->sum('balance_effect'), 2);
    }

    private function computedAdvanceStatus(float $balance, float $adjusted): string
    {
        if ($balance <= 0) {
            return 'Fully Adjusted';
        }

        if ($adjusted > 0) {
            return 'Partially Adjusted';
        }

        return 'Open';
    }

    private function rulePayload(array $effects): Collection
    {
        return LedgerMappingRule::query()
            ->with(['transactionHead', 'settlementType', 'debitAccount', 'creditAccount'])
            ->where('status', 'Active')
            ->whereIn('party_ledger_effect', $effects)
            ->orderBy('party_ledger_effect')
            ->get()
            ->map(fn (LedgerMappingRule $rule) => [
                'transaction_head_id' => $rule->transaction_head_id,
                'settlement_type_id' => $rule->settlement_type_id,
                'advance_type' => match ($rule->party_ledger_effect) {
                    'Increase Advance Asset', 'Decrease Advance Asset' => 'Paid',
                    'Increase Advance Liability', 'Decrease Advance Liability' => 'Received',
                    default => null,
                },
                'entry_mode' => str_starts_with($rule->party_ledger_effect, 'Increase') ? 'New Advance' : 'Advance Adjustment',
                'label' => trim(($rule->transactionHead?->name ?? 'Transaction Head') . ' - ' . ($rule->settlementType?->name ?? 'Settlement')),
                'effect' => $rule->party_ledger_effect,
                'debit_account' => $rule->debitAccount?->account_name,
                'credit_account' => $rule->creditAccount?->account_name,
                'requires_cash_bank' => (bool) ($rule->debitAccount?->is_cash_bank || $rule->creditAccount?->is_cash_bank),
            ])
            ->values();
    }

    private function expectedEffect(string $entryMode, string $advanceType): string
    {
        return match ([$entryMode, $advanceType]) {
            ['New Advance', 'Paid'] => 'Increase Advance Asset',
            ['New Advance', 'Received'] => 'Increase Advance Liability',
            ['Advance Adjustment', 'Paid'] => 'Decrease Advance Asset',
            ['Advance Adjustment', 'Received'] => 'Decrease Advance Liability',
            default => 'No Effect',
        };
    }

    private function effectErrorMessage(string $entryMode, string $advanceType): string
    {
        return match ([$entryMode, $advanceType]) {
            ['New Advance', 'Paid'] => 'Select an Advance Paid rule that debits Advance to Supplier / Employee and credits Cash/Bank.',
            ['New Advance', 'Received'] => 'Select an Advance Received rule that debits Cash/Bank and credits Advance from Customer.',
            ['Advance Adjustment', 'Paid'] => 'Select an Advance Paid Adjustment rule that debits Accounts Payable and credits Advance to Supplier / Employee.',
            ['Advance Adjustment', 'Received'] => 'Select an Advance Received Adjustment rule that debits Advance from Customer and credits Accounts Receivable.',
            default => 'Selected advance rule does not match the requested advance type.',
        };
    }

    private function validatePreviewTouchesAdvanceAccount(array $preview, int $accountId, string $advanceType): void
    {
        $entries = collect($preview['entries'] ?? []);
        $expectedEntryType = $advanceType === 'Paid' ? 'Credit' : 'Debit';

        $matched = $entries->contains(fn (array $entry) =>
            (int) ($entry['account_id'] ?? 0) === $accountId
            && ($entry['entry_type'] ?? null) === $expectedEntryType
        );

        if (!$matched) {
            throw ValidationException::withMessages([
                'transaction_head_id' => $advanceType === 'Paid'
                    ? 'The selected rule must credit the same Advance to Supplier / Employee ledger used by this advance balance.'
                    : 'The selected rule must debit the same Advance from Customer ledger used by this advance balance.',
            ]);
        }
    }

    private function validateLinkedDueBalance(array $preview, int $partyId, string $advanceType, float $amount): void
    {
        $entries = collect($preview['entries'] ?? []);

        if ($advanceType === 'Paid') {
            $dueEntry = $entries->first(fn (array $entry) =>
                ($entry['entry_type'] ?? null) === 'Debit'
                && ($entry['account_type'] ?? null) === 'Liability'
            );

            if (!$dueEntry) {
                return;
            }

            $balance = $this->currentDueBalance($partyId, (int) $dueEntry['account_id'], 'Payable');

            if ($balance < $amount) {
                throw ValidationException::withMessages([
                    'amount' => 'Advance paid adjustment cannot be greater than the current payable due for this party. Record or select the supplier due first.',
                ]);
            }

            return;
        }

        $dueEntry = $entries->first(fn (array $entry) =>
            ($entry['entry_type'] ?? null) === 'Credit'
            && ($entry['account_type'] ?? null) === 'Asset'
        );

        if (!$dueEntry) {
            return;
        }

        $balance = $this->currentDueBalance($partyId, (int) $dueEntry['account_id'], 'Receivable');

        if ($balance < $amount) {
            throw ValidationException::withMessages([
                'amount' => 'Advance received adjustment cannot be greater than the current receivable due for this party. Record or select the customer receivable first.',
            ]);
        }
    }
}
