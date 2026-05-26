<?php

namespace App\Http\Controllers\Api;

use App\AccountingReports\Services\AccountingReportService;
use App\Http\Controllers\Controller;
use App\Models\AccountType;
use App\Models\AccountingRule;
use App\Models\AccountingRuleLine;
use App\Models\ChartOfAccount;
use App\Models\LedgerMappingRule;
use App\Models\VoucherNumberingRule;
use App\Services\Setup\ChartOfAccountService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class SrsApiController extends Controller
{
    public function __construct(private readonly AccountingReportService $reports)
    {
    }

    public function accounts(Request $request): JsonResponse
    {
        $companyId = (int) ($request->user()?->company_id ?? 0);

        $accounts = ChartOfAccount::query()
            ->with(['accountType:id,name,normal_balance', 'parent:id,account_code,account_name', 'partyType:id,name'])
            ->when($companyId > 0, fn ($query) => $query->where('company_id', $companyId))
            ->when($request->boolean('posting_only'), fn ($query) => $query->postingLedgers())
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->input('status')))
            ->when($request->filled('q'), function ($query) use ($request) {
                $q = '%' . mb_strtolower((string) $request->input('q')) . '%';
                $query->where(function ($where) use ($q) {
                    $where->whereRaw('LOWER(account_code) LIKE ?', [$q])
                        ->orWhereRaw('LOWER(account_name) LIKE ?', [$q]);
                });
            })
            ->orderBy('account_code')
            ->paginate((int) $request->integer('per_page', 50))
            ->withQueryString();

        return response()->json([
            'success' => true,
            'data' => $accounts,
        ]);
    }

    public function storeAccount(Request $request, ChartOfAccountService $service): JsonResponse
    {
        $companyId = (int) ($request->user()?->company_id ?? 0);

        $data = $request->validate([
            'account_code' => ['required', 'string', 'max:30', Rule::unique('chart_of_accounts', 'account_code')->where(fn ($query) => $companyId > 0 ? $query->where('company_id', $companyId) : $query)],
            'account_name' => ['required', 'string', 'max:255'],
            'account_type_id' => ['required', 'integer', Rule::exists('account_types', 'id')->where(fn ($query) => $query->where('status', 'Active'))],
            'parent_id' => ['nullable', 'integer', 'exists:chart_of_accounts,id'],
            'coa_level' => ['nullable', 'integer', 'between:1,4'],
            'account_level' => ['nullable', Rule::in(['Class', 'Group', 'Sub Group', 'Ledger'])],
            'normal_balance' => ['nullable', Rule::in(['Debit', 'Credit'])],
            'ledger_type' => ['nullable', 'string', 'max:50'],
            'posting_allowed' => ['nullable', 'boolean'],
            'is_cash_bank' => ['nullable', 'boolean'],
            'is_party_control' => ['nullable', 'boolean'],
            'party_type_id' => ['nullable', 'integer', 'exists:party_types,id'],
            'status' => ['nullable', Rule::in(['Active', 'Inactive'])],
            'description' => ['nullable', 'string', 'max:1000'],
        ]);

        $accountType = AccountType::query()->find($data['account_type_id']);
        $data['company_id'] = $companyId ?: null;
        $data['coa_level'] = isset($data['coa_level'])
            ? (int) $data['coa_level']
            : (($data['account_level'] ?? null) === 'Ledger' ? 4 : 1);
        $data['account_level'] = $data['account_level'] ?? ($data['coa_level'] === 4 ? 'Ledger' : 'Group');
        $data['normal_balance'] = $data['normal_balance'] ?? $accountType?->normal_balance ?? 'Debit';
        $data['account_nature'] = $accountType?->name;
        $data['posting_allowed'] = (bool) ($data['posting_allowed'] ?? $data['coa_level'] === 4);
        $data['status'] = $data['status'] ?? 'Active';

        $account = $service->create($data, $request->user()?->id);

        return response()->json([
            'success' => true,
            'message' => 'Account created successfully.',
            'data' => $account->load(['accountType', 'parent', 'partyType']),
        ], 201);
    }

    public function voucherNumbering(Request $request): JsonResponse
    {
        $companyId = (int) ($request->user()?->company_id ?? 0);

        return response()->json([
            'success' => true,
            'data' => VoucherNumberingRule::query()
                ->when($companyId > 0, fn ($query) => $query->where('company_id', $companyId))
                ->orderBy('voucher_type')
                ->orderBy('prefix')
                ->get(),
        ]);
    }

    public function transactionPurposes(Request $request): JsonResponse
    {
        $companyId = (int) ($request->user()?->company_id ?? 0);

        return response()->json([
            'success' => true,
            'data' => DB::table('transaction_heads')
                ->when($companyId > 0, fn ($query) => $query->where('company_id', $companyId))
                ->where('status', 'Active')
                ->whereNull('deleted_at')
                ->orderBy('name')
                ->get(),
        ]);
    }

    public function storeAccountingRule(Request $request): JsonResponse
    {
        if ($request->has('lines')) {
            return $this->storeAccountingRuleLines($request);
        }

        return $this->storeLegacyLedgerMappingRule($request);
    }

    private function storeAccountingRuleLines(Request $request): JsonResponse
    {
        $companyId = (int) ($request->user()?->company_id ?? 0);

        $data = $request->validate([
            'transaction_head_id' => ['required', 'integer', 'exists:transaction_heads,id'],
            'settlement_type_id' => ['nullable', 'integer', 'exists:settlement_types,id'],
            'rule_code' => ['required', 'string', 'max:30'],
            'rule_name' => ['required', 'string', 'max:150'],
            'transaction_screen' => ['nullable', 'string', 'max:100'],
            'party_required_mode' => ['nullable', Rule::in(['No', 'Yes', 'Optional'])],
            'party_type_id' => ['nullable', 'integer', 'exists:party_types,id'],
            'payment_method_required' => ['nullable', 'boolean'],
            'allowed_payment_methods' => ['nullable', 'array'],
            'cash_bank_ledger_required' => ['nullable', 'boolean'],
            'party_ledger_effect' => ['nullable', 'string', 'max:100'],
            'auto_post' => ['nullable', 'boolean'],
            'description' => ['nullable', 'string', 'max:1000'],
            'status' => ['nullable', Rule::in(['Draft', 'Pending Review', 'Active', 'Inactive'])],
            'lines' => ['required', 'array', 'min:2'],
            'lines.*.line_role' => ['nullable', 'string', 'max:30'],
            'lines.*.side' => ['required', Rule::in(['Debit', 'Credit', 'Dr', 'Cr'])],
            'lines.*.ledger_source' => ['nullable', 'string', 'max:50'],
            'lines.*.account_source_type' => ['nullable', 'string', 'max:80'],
            'lines.*.ledger_id' => ['nullable', 'integer', 'exists:chart_of_accounts,id'],
            'lines.*.fixed_ledger_id' => ['nullable', 'integer', 'exists:chart_of_accounts,id'],
            'lines.*.movement' => ['nullable', 'string', 'max:20'],
            'lines.*.selection_method' => ['nullable', 'string', 'max:80'],
            'lines.*.allowed_ledger_type' => ['nullable', 'string', 'max:80'],
            'lines.*.amount_source' => ['nullable', 'string', 'max:50'],
            'lines.*.amount_formula' => ['nullable', 'string', 'max:255'],
            'lines.*.explanation' => ['nullable', 'string', 'max:1000'],
            'lines.*.sort_order' => ['nullable', 'integer', 'min:1'],
        ]);

        $hasDebit = collect($data['lines'])->contains(fn (array $line) => in_array($line['side'], ['Debit', 'Dr'], true));
        $hasCredit = collect($data['lines'])->contains(fn (array $line) => in_array($line['side'], ['Credit', 'Cr'], true));

        abort_unless($hasDebit && $hasCredit, 422, 'Accounting rule must have at least one debit line and one credit line.');

        foreach ($data['lines'] as $index => $line) {
            $source = $this->normaliseRuleLineLedgerSource($line['ledger_source'] ?? $line['account_source_type'] ?? null);
            $ledgerId = $line['ledger_id'] ?? $line['fixed_ledger_id'] ?? null;

            if ($source === 'fixed' && ! $ledgerId) {
                abort(422, 'Fixed ledger is required for rule line #' . ($index + 1) . '.');
            }

            if ($ledgerId) {
                $ledger = ChartOfAccount::query()->find($ledgerId);
                $isPostingLedger = $ledger
                    && $ledger->status === 'Active'
                    && (bool) $ledger->posting_allowed
                    && ((int) $ledger->coa_level === 4 || $ledger->account_level === 'Ledger');

                abort_unless($isPostingLedger, 422, 'Rule line #' . ($index + 1) . ' must use an active Level 4 posting ledger.');
            }
        }

        $rule = DB::transaction(function () use ($data, $companyId, $request) {
            $rule = AccountingRule::query()->updateOrCreate(
                [
                    'company_id' => $companyId ?: null,
                    'rule_code' => $data['rule_code'],
                ],
                [
                    'rule_name' => $data['rule_name'],
                    'transaction_head_id' => $data['transaction_head_id'],
                    'settlement_type_id' => $data['settlement_type_id'] ?? null,
                    'transaction_screen' => $data['transaction_screen'] ?? null,
                    'rule_trigger' => 'Transaction Head selected',
                    'amount_required' => true,
                    'party_required_mode' => $data['party_required_mode'] ?? 'No',
                    'party_type_id' => $data['party_type_id'] ?? null,
                    'payment_method_required' => (bool) ($data['payment_method_required'] ?? false),
                    'allowed_payment_methods' => $data['allowed_payment_methods'] ?? null,
                    'cash_bank_ledger_required' => (bool) ($data['cash_bank_ledger_required'] ?? false),
                    'party_ledger_effect' => $data['party_ledger_effect'] ?? 'No Effect',
                    'auto_post' => (bool) ($data['auto_post'] ?? true),
                    'description' => $data['description'] ?? null,
                    'status' => $data['status'] ?? 'Active',
                    'updated_by' => $request->user()?->id,
                    'created_by' => $request->user()?->id,
                ]
            );

            $rule->lines()->delete();

            foreach (array_values($data['lines']) as $index => $line) {
                AccountingRuleLine::query()->create([
                    'accounting_rule_id' => $rule->id,
                    'line_role' => $line['line_role'] ?? ($index === 0 ? 'primary' : 'counter'),
                    'ledger_source' => $this->normaliseRuleLineLedgerSource($line['ledger_source'] ?? $line['account_source_type'] ?? null),
                    'ledger_id' => $line['ledger_id'] ?? $line['fixed_ledger_id'] ?? null,
                    'side' => $this->normaliseRuleSide($line['side']),
                    'movement' => $line['movement'] ?? null,
                    'selection_method' => $line['selection_method'] ?? null,
                    'allowed_ledger_type' => $line['allowed_ledger_type'] ?? null,
                    'amount_source' => $line['amount_source'] ?? 'transaction_amount',
                    'amount_formula' => $line['amount_formula'] ?? null,
                    'explanation' => $line['explanation'] ?? null,
                    'sort_order' => $line['sort_order'] ?? ($index + 1),
                ]);
            }

            return $rule->fresh(['transactionHead', 'settlementType', 'lines.ledger']);
        });

        return response()->json([
            'success' => true,
            'message' => 'Accounting rule saved successfully.',
            'data' => $rule,
        ], 201);
    }

    private function storeLegacyLedgerMappingRule(Request $request): JsonResponse
    {
        $companyId = (int) ($request->user()?->company_id ?? 0);

        $data = $request->validate([
            'transaction_head_id' => ['required', 'integer', 'exists:transaction_heads,id'],
            'settlement_type_id' => ['required', 'integer', 'exists:settlement_types,id'],
            'debit_account_id' => ['required', 'integer', 'exists:chart_of_accounts,id'],
            'credit_account_id' => ['required', 'integer', 'exists:chart_of_accounts,id'],
            'rule_code' => ['nullable', 'string', 'max:50'],
            'party_ledger_effect' => ['nullable', 'string', 'max:80'],
            'auto_post' => ['nullable', 'boolean'],
            'description' => ['nullable', 'string', 'max:1000'],
            'status' => ['nullable', Rule::in(['Active', 'Inactive', 'Draft'])],
        ]);

        $payload = array_merge($data, [
            'company_id' => $companyId ?: null,
            'auto_post' => (bool) ($data['auto_post'] ?? true),
            'status' => $data['status'] ?? 'Active',
            'created_by' => $request->user()?->id,
            'updated_by' => $request->user()?->id,
        ]);

        $rule = LedgerMappingRule::query()->updateOrCreate(
            [
                'company_id' => $payload['company_id'],
                'transaction_head_id' => $payload['transaction_head_id'],
                'settlement_type_id' => $payload['settlement_type_id'],
            ],
            $payload
        );

        return response()->json([
            'success' => true,
            'message' => 'Legacy ledger mapping rule saved successfully.',
            'data' => $rule->fresh(['transactionHead', 'settlementType', 'debitAccount', 'creditAccount']),
        ]);
    }

    private function normaliseRuleSide(string $side): string
    {
        return match ($side) {
            'Dr' => 'Debit',
            'Cr' => 'Credit',
            default => $side,
        };
    }

    private function normaliseRuleLineLedgerSource(?string $source): string
    {
        $source = strtolower(trim((string) $source));

        return match ($source) {
            'fixed', 'fixed_ledger', 'fixed ledger' => 'fixed',
            'payment', 'receiving', 'user_selected_cash_bank', 'user_cash_bank', 'cash_bank', 'cash/bank' => 'user_cash_bank',
            'customer_receivable', 'supplier_payable', 'salary_payable', 'party_control', 'party' => 'party_control',
            'transaction_head', 'transaction head' => 'transaction_head',
            'system_derived', 'system derived' => 'system_derived',
            default => $source ?: 'fixed',
        };
    }

    public function vouchers(Request $request): JsonResponse
    {
        $companyId = (int) ($request->user()?->company_id ?? 0);

        $rows = DB::table('voucher_headers as v')
            ->leftJoin('transaction_heads as th', 'th.id', '=', 'v.transaction_head_id')
            ->leftJoin('parties as p', 'p.id', '=', 'v.party_id')
            ->whereNull('v.deleted_at')
            ->when($companyId > 0, fn ($query) => $query->where('v.company_id', $companyId))
            ->select('v.id', 'v.voucher_number', 'v.voucher_type', 'v.voucher_date', 'v.amount', 'v.status', 'v.reference', 'v.notes', 'th.name as transaction_head', 'p.party_name')
            ->orderByDesc('v.voucher_date')
            ->orderByDesc('v.id')
            ->paginate((int) $request->integer('per_page', 50));

        return response()->json(['success' => true, 'data' => $rows]);
    }

    public function journalEntries(Request $request): JsonResponse
    {
        $companyId = (int) ($request->user()?->company_id ?? 0);

        $entries = DB::table('journal_headers as jh')
            ->leftJoin('voucher_headers as v', 'v.id', '=', 'jh.voucher_header_id')
            ->when($companyId > 0, fn ($query) => $query->where('jh.company_id', $companyId))
            ->select('jh.*', 'v.voucher_number')
            ->orderByDesc('jh.journal_date')
            ->orderByDesc('jh.id')
            ->paginate((int) $request->integer('per_page', 50));

        return response()->json(['success' => true, 'data' => $entries]);
    }

    public function generalLedger(Request $request): JsonResponse
    {
        $companyId = (int) ($request->user()?->company_id ?? 0);
        $accountId = $request->integer('account_id');

        $rows = DB::table('journal_lines as jl')
            ->join('journal_headers as jh', 'jh.id', '=', 'jl.journal_header_id')
            ->join('chart_of_accounts as coa', 'coa.id', '=', 'jl.ledger_id')
            ->leftJoin('voucher_headers as v', 'v.id', '=', 'jh.voucher_header_id')
            ->whereIn('jh.status', ['Posted', 'Reversed'])
            ->when($companyId > 0, fn ($query) => $query->where('jh.company_id', $companyId))
            ->when($accountId > 0, fn ($query) => $query->where('jl.ledger_id', $accountId))
            ->when($request->filled('from_date'), fn ($query) => $query->whereDate('jh.journal_date', '>=', $request->input('from_date')))
            ->when($request->filled('to_date'), fn ($query) => $query->whereDate('jh.journal_date', '<=', $request->input('to_date')))
            ->select('jh.journal_date', 'jh.journal_no', 'v.voucher_number', 'coa.account_code', 'coa.account_name', 'jl.debit_amount', 'jl.credit_amount', 'jl.line_narration')
            ->orderBy('jh.journal_date')
            ->orderBy('jl.id')
            ->paginate((int) $request->integer('per_page', 50));

        return response()->json(['success' => true, 'data' => $rows]);
    }

    public function trialBalance(Request $request): JsonResponse
    {
        return $this->reportResponse($this->reports->trialBalance($this->reportFilters($request)));
    }

    public function profitLoss(Request $request): JsonResponse
    {
        return $this->reportResponse($this->reports->incomeStatement($this->reportFilters($request)));
    }

    public function balanceSheet(Request $request): JsonResponse
    {
        return $this->reportResponse($this->reports->balanceSheet($this->reportFilters($request)));
    }

    public function customerDue(Request $request): JsonResponse
    {
        return $this->reportResponse($this->reports->customerReceivables($this->reportFilters($request)));
    }

    public function supplierDue(Request $request): JsonResponse
    {
        return $this->reportResponse($this->reports->supplierPayables($this->reportFilters($request)));
    }

    /**
     * @return array<string, mixed>
     */
    private function reportFilters(Request $request): array
    {
        return array_filter([
            'company_id' => (int) ($request->user()?->company_id ?? 0) ?: null,
            'from_date' => $request->input('from_date'),
            'to_date' => $request->input('to_date'),
            'as_of_date' => $request->input('as_of_date'),
            'account_id' => $request->input('account_id'),
            'party_id' => $request->input('party_id'),
            'include_zero_balances' => $request->boolean('include_zero_balances'),
        ], fn ($value) => $value !== null && $value !== '');
    }

    private function reportResponse(array $report): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $report,
        ]);
    }
}
