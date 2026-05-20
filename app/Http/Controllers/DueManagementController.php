<?php

namespace App\Http\Controllers;

use App\Models\CashBankAccount;
use App\Models\ChartOfAccount;
use App\Models\DueRegister;
use App\Models\LedgerMappingRule;
use App\Models\Party;
use App\Models\VoucherDetail;
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
            'parties' => Party::query()
                ->where('status', 'Active')
                ->with(['partyType', 'linkedLedger.accountType'])
                ->orderBy('party_name')
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

        $amountCents = $this->moneyToCents($amount);
        $balanceCents = max(0, $this->moneyToCents($balance));

        if ($balanceCents <= 0) {
            throw ValidationException::withMessages([
                'amount' => 'This party has no outstanding ' . strtolower((string) $validated['due_type']) . ' balance in the selected ledger.',
            ]);
        }

        if ($amountCents > $balanceCents) {
            throw ValidationException::withMessages([
                'amount' => 'Payment / collection amount cannot be greater than the current due balance. Current balance is BDT ' . number_format($balanceCents / 100, 2) . '.',
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

        if ($this->effectiveDueEffect($rule) !== $expectedEffect) {
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
                    'remaining_balance' => max(0, ($balanceCents - $amountCents) / 100),
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
        $registerRows = $this->registeredDueRows();
        $ledgerRows = $this->ledgerDueRows();

        return $registerRows
            ->merge($ledgerRows)
            ->groupBy(fn (array $row) => $row['party_id'] . '|' . $row['account_id'] . '|' . $row['due_type'])
            ->map(function (Collection $group) {
                $first = $group->sortByDesc(fn (array $row) => $row['source_priority'] ?? 0)->first();
                $registered = $group->firstWhere('source', 'register');
                $ledger = $group->firstWhere('source', 'ledger');

                $balance = max(
                    0,
                    (float) ($registered['balance_due'] ?? 0),
                    (float) ($ledger['balance_due'] ?? 0)
                );

                $original = max(
                    (float) ($registered['original_amount'] ?? 0),
                    (float) ($ledger['original_amount'] ?? 0)
                );

                $settled = max(
                    (float) ($registered['settled_amount'] ?? 0),
                    (float) ($ledger['settled_amount'] ?? 0)
                );

                if ($original <= 0 && $balance <= 0) {
                    return null;
                }

                $dueDate = $registered['due_date'] ?? $ledger['due_date'] ?? null;
                $status = $this->computedDueStatus($balance, $settled, $dueDate);

                return array_merge($first, [
                    'original_amount' => round($original, 2),
                    'settled_amount' => round($settled, 2),
                    'balance_due' => round($balance, 2),
                    'raw_balance' => round($balance, 2),
                    'due_date' => $dueDate,
                    'status' => $status,
                    'latest_voucher' => $registered['latest_voucher'] ?? $ledger['latest_voucher'] ?? null,
                    'latest_voucher_date' => $registered['latest_voucher_date'] ?? $ledger['latest_voucher_date'] ?? null,
                    'movement_count' => (int) ($registered['movement_count'] ?? 0) + (int) ($ledger['movement_count'] ?? 0),
                ]);
            })
            ->filter()
            ->sortBy([
                ['due_type', 'asc'],
                ['due_date', 'asc'],
                ['party_name', 'asc'],
            ])
            ->values();
    }

    private function registeredDueRows(): Collection
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
                $balance = round((float) $group->sum('balance_effect'), 2);

                $dueDate = $increaseRows
                    ->map(fn (DueRegister $movement) => $movement->due_date ?: $movement->voucherHeader?->voucher_date)
                    ->filter()
                    ->sort()
                    ->first();

                $latest = $group
                    ->sortByDesc(fn (DueRegister $movement) => $movement->voucherHeader?->voucher_date?->timestamp ?? 0)
                    ->first();

                return [
                    'source' => 'register',
                    'source_priority' => 2,
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
                    'status' => $this->computedDueStatus($balance, $settled, $dueDate),
                    'latest_voucher' => $latest?->voucherHeader?->voucher_number,
                    'latest_voucher_date' => $latest?->voucherHeader?->voucher_date?->toDateString(),
                    'movement_count' => $group->count(),
                ];
            })
            ->filter()
            ->values();
    }

    private function ledgerDueRows(): Collection
    {
        $rows = VoucherDetail::query()
            ->selectRaw('voucher_details.party_id, voucher_headers.party_id as header_party_id, voucher_details.account_id, chart_of_accounts.account_name, chart_of_accounts.account_code, account_types.name as account_type, parties.party_name, party_types.name as party_type_name, COALESCE(SUM(voucher_details.debit), 0) as total_debit, COALESCE(SUM(voucher_details.credit), 0) as total_credit, MIN(voucher_headers.voucher_date) as first_date, MAX(voucher_headers.voucher_date) as latest_date, MAX(voucher_headers.voucher_number) as latest_voucher, COUNT(*) as movement_count')
            ->join('voucher_headers', 'voucher_headers.id', '=', 'voucher_details.voucher_header_id')
            ->join('chart_of_accounts', 'chart_of_accounts.id', '=', 'voucher_details.account_id')
            ->leftJoin('account_types', 'account_types.id', '=', 'chart_of_accounts.account_type_id')
            ->leftJoin('parties', 'parties.id', '=', \DB::raw('COALESCE(voucher_details.party_id, voucher_headers.party_id)'))
            ->leftJoin('party_types', 'party_types.id', '=', 'parties.party_type_id')
            ->where('voucher_headers.status', VoucherHeader::STATUS_POSTED)
            ->whereNotNull(\DB::raw('COALESCE(voucher_details.party_id, voucher_headers.party_id)'))
            ->where(function ($query) {
                $query->where(function ($inner) {
                    $inner->where('account_types.name', 'Liability')
                        ->where(function ($name) {
                            $name->where('chart_of_accounts.account_name', 'like', '%Payable%')
                                ->orWhere('chart_of_accounts.account_name', 'like', '%Supplier Due%');
                        });
                })->orWhere(function ($inner) {
                    $inner->where('account_types.name', 'Asset')
                        ->where(function ($name) {
                            $name->where('chart_of_accounts.account_name', 'like', '%Receivable%')
                                ->orWhere('chart_of_accounts.account_name', 'like', '%Customer Due%');
                        });
                });
            })
            ->groupBy('voucher_details.party_id', 'voucher_headers.party_id', 'voucher_details.account_id', 'chart_of_accounts.account_name', 'chart_of_accounts.account_code', 'account_types.name', 'parties.party_name', 'party_types.name')
            ->get();

        return $rows->map(function ($row) {
            $partyId = (int) ($row->party_id ?: $row->header_party_id);
            $accountType = (string) $row->account_type;
            $dueType = $accountType === 'Liability' ? 'Payable' : 'Receivable';
            $debit = round((float) $row->total_debit, 2);
            $credit = round((float) $row->total_credit, 2);
            $balance = $dueType === 'Payable' ? $credit - $debit : $debit - $credit;
            $original = $dueType === 'Payable' ? $credit : $debit;
            $settled = $dueType === 'Payable' ? $debit : $credit;

            return [
                'source' => 'ledger',
                'source_priority' => 1,
                'key' => $partyId . '|' . $row->account_id . '|' . $dueType,
                'party_id' => $partyId,
                'party_name' => $row->party_name ?: 'Unknown Party',
                'party_type' => $row->party_type_name,
                'account_id' => (int) $row->account_id,
                'account_name' => trim(($row->account_code ? $row->account_code . ' - ' : '') . $row->account_name),
                'account_type' => $accountType,
                'due_type' => $dueType,
                'original_amount' => round($original, 2),
                'settled_amount' => round($settled, 2),
                'balance_due' => max(0, round($balance, 2)),
                'raw_balance' => round($balance, 2),
                'due_date' => $row->first_date ? Carbon::parse($row->first_date)->toDateString() : null,
                'status' => $this->computedDueStatus($balance, $settled, $row->first_date),
                'latest_voucher' => $row->latest_voucher,
                'latest_voucher_date' => $row->latest_date ? Carbon::parse($row->latest_date)->toDateString() : null,
                'movement_count' => (int) $row->movement_count,
            ];
        })->filter(fn (array $row) => $row['original_amount'] > 0 || $row['balance_due'] > 0)->values();
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
        $registered = round((float) DueRegister::query()
            ->where('party_id', $partyId)
            ->where('account_id', $accountId)
            ->where('due_type', $dueType)
            ->whereHas('voucherHeader', fn ($query) => $query->where('status', VoucherHeader::STATUS_POSTED))
            ->sum('balance_effect'), 2);

        $ledger = $this->ledgerBalanceFor($partyId, $accountId, $dueType);

        return round(max($registered, $ledger), 2);
    }

    private function ledgerBalanceFor(int $partyId, int $accountId, string $dueType): float
    {
        $totals = VoucherDetail::query()
            ->selectRaw('COALESCE(SUM(voucher_details.debit), 0) as total_debit, COALESCE(SUM(voucher_details.credit), 0) as total_credit')
            ->join('voucher_headers', 'voucher_headers.id', '=', 'voucher_details.voucher_header_id')
            ->where('voucher_headers.status', VoucherHeader::STATUS_POSTED)
            ->where('voucher_details.account_id', $accountId)
            ->where(function ($query) use ($partyId) {
                $query->where('voucher_details.party_id', $partyId)
                    ->orWhere('voucher_headers.party_id', $partyId);
            })
            ->first();

        $debit = (float) ($totals?->total_debit ?? 0);
        $credit = (float) ($totals?->total_credit ?? 0);

        return round($dueType === 'Payable' ? $credit - $debit : $debit - $credit, 2);
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
            ->with(['transactionHead', 'settlementType', 'debitAccount.accountType', 'creditAccount.accountType'])
            ->where('status', 'Active')
            ->orderBy('rule_code')
            ->get()
            ->map(function (LedgerMappingRule $rule) {
                $effect = $this->effectiveDueEffect($rule);

                if (!in_array($effect, ['Decrease Liability', 'Decrease Receivable'], true)) {
                    return null;
                }

                return [
                    'transaction_head_id' => $rule->transaction_head_id,
                    'settlement_type_id' => $rule->settlement_type_id,
                    'due_type' => $effect === 'Decrease Liability' ? 'Payable' : 'Receivable',
                    'label' => trim(($rule->transactionHead?->name ?? 'Transaction Head') . ' - ' . ($rule->settlementType?->name ?? 'Settlement')),
                    'effect' => $effect,
                    'debit_account' => $rule->debitAccount?->account_name,
                    'credit_account' => $rule->creditAccount?->account_name,
                ];
            })
            ->filter()
            ->unique(fn (array $row) => $row['transaction_head_id'] . '|' . $row['settlement_type_id'] . '|' . $row['effect'])
            ->values();
    }

    private function effectiveDueEffect(LedgerMappingRule $rule): string
    {
        $stored = (string) ($rule->party_ledger_effect ?? 'No Effect');

        if (in_array($stored, ['Decrease Liability', 'Decrease Receivable'], true)) {
            return $stored;
        }

        $headText = strtoupper(trim(($rule->transactionHead?->nature ?? '') . ' ' . ($rule->transactionHead?->name ?? '')));
        $settlementText = strtoupper(trim(($rule->settlementType?->code ?? '') . ' ' . ($rule->settlementType?->name ?? '')));
        $debitType = $rule->debitAccount?->accountType?->name;
        $creditType = $rule->creditAccount?->accountType?->name;
        $debitName = strtoupper((string) ($rule->debitAccount?->account_name ?? ''));
        $creditName = strtoupper((string) ($rule->creditAccount?->account_name ?? ''));
        $debitCash = (bool) $rule->debitAccount?->is_cash_bank;
        $creditCash = (bool) $rule->creditAccount?->is_cash_bank;

        $looksPayable = str_contains($debitName, 'PAYABLE') || str_contains($debitName, 'SUPPLIER DUE');
        $looksReceivable = str_contains($creditName, 'RECEIVABLE') || str_contains($creditName, 'CUSTOMER DUE');
        $cashOrBank = str_contains($settlementText, 'CASH') || str_contains($settlementText, 'BANK');

        if ($cashOrBank && $creditCash && $debitType === 'Liability' && ($looksPayable || str_contains($headText, 'SUPPLIER PAYMENT') || str_contains($headText, 'DUE PAYMENT'))) {
            return 'Decrease Liability';
        }

        if ($cashOrBank && $debitCash && $creditType === 'Asset' && ($looksReceivable || str_contains($headText, 'CUSTOMER PAYMENT') || str_contains($headText, 'COLLECTION'))) {
            return 'Decrease Receivable';
        }

        return $stored ?: 'No Effect';
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

    private function moneyToCents(float|int|string $value): int
    {
        return (int) round(((float) $value) * 100);
    }
}
