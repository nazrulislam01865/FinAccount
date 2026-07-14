<?php

namespace App\Services\Accounting;

use App\Models\AccountingRule;
use App\Models\Company;
use App\Models\JournalEntry;
use App\Models\MoneyAccount;
use App\Models\Transaction;
use App\Models\TransactionHead;
use App\Models\User;
use App\Services\Company\CompanyAccountingPeriodService;
use App\Support\SaleSellingTypes;
use App\Support\TransactionTypes;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class TransactionPostingService
{
    public function __construct(
        private readonly JournalBuilder $journalBuilder,
        private readonly VoucherNumberService $voucherNumberService,
        private readonly DecimalAmount $decimalAmount,
        private readonly TransactionSettlementService $settlementService,
        private readonly RuleMatcher $ruleMatcher,
        private readonly SalesInvoiceService $salesInvoiceService,
        private readonly CompanyAccountingPeriodService $accountingPeriodService,
        private readonly TransactionPartyResolver $partyResolver,
    ) {}

    /** @param array<string, mixed> $data */
    public function post(array $data, User $user): Transaction
    {
        return $this->postUsingHead($data, $user, null, false);
    }

    /**
     * Post through a trusted module-owned transaction head.
     *
     * The normal Transaction Entry endpoint cannot select SYS-FEED heads. The Feed
     * module uses this method after resolving its own dedicated SALE/PURCHASE head,
     * so the same central rule matcher, journal builder, voucher and invoice logic
     * are used without exposing stock-changing heads in the generic form.
     *
     * @param array<string, mixed> $data
     */
    public function postForHead(array $data, User $user, TransactionHead $transactionHead): Transaction
    {
        return $this->postUsingHead($data, $user, $transactionHead, true);
    }

    /** @param array<string, mixed> $data */
    private function postUsingHead(
        array $data,
        User $user,
        ?TransactionHead $trustedHead,
        bool $allowInternalHead,
    ): Transaction {
        if (! $user->company_id) {
            throw ValidationException::withMessages([
                'company' => 'Your user account is not connected to a company.',
            ]);
        }

        return DB::transaction(function () use ($data, $user, $trustedHead, $allowInternalHead): Transaction {
            $company = Company::query()
                ->with(['defaultFinancialYear', 'currency', 'timeZone'])
                ->lockForUpdate()
                ->findOrFail($user->company_id);

            $this->accountingPeriodService->assertPostingAllowed($company, (string) $data['transaction_date']);

            $transactionType = (string) $data['category'];
            $headId = $trustedHead?->id ?? ($data['transaction_head_id'] ?? null);

            $head = TransactionHead::query()
                ->with('postingAccount')
                ->where('company_id', $user->company_id)
                ->whereRaw('LOWER(category) = ?', [strtolower($transactionType)])
                ->where('is_active', true)
                ->when(! $allowInternalHead, fn ($query) => $query->where('code', 'not like', 'SYS-FEED-%'))
                ->whereNotNull('posting_account_id')
                ->whereHas('postingAccount', fn ($query) => $query
                    ->where('company_id', $user->company_id)
                    ->where('is_active', true))
                ->findOrFail($headId);

            if ($trustedHead && (int) $trustedHead->id !== (int) $head->id) {
                throw ValidationException::withMessages([
                    'transaction_head_id' => 'The requested module transaction head is no longer available.',
                ]);
            }

            $this->validateHeadNature($head, $transactionType);

            $existing = Transaction::query()
                ->where('company_id', $user->company_id)
                ->where('request_token', $data['request_token'])
                ->first();

            if ($existing) {
                return $this->matchingExistingTransaction($existing, $head, $transactionType);
            }

            $moneyAccount = filled($data['money_account_id'] ?? null)
                ? MoneyAccount::query()
                    ->with('chartOfAccount')
                    ->where('company_id', $user->company_id)
                    ->where('is_active', true)
                    ->whereNotNull('chart_of_account_id')
                    ->whereHas('chartOfAccount', fn ($query) => $query
                        ->where('company_id', $user->company_id)
                        ->where('is_active', true))
                    ->findOrFail($data['money_account_id'])
                : null;

            $scale = (int) ($company->currency?->decimal_places ?? 2);
            $amount = $this->decimalAmount->normalize($data['amount'], $scale);
            $settlement = $this->settlementService->prepare($amount, $data, $scale);
            $settlementType = $settlement['settlement_type'];

            if (! $head->allowsSettlement($settlementType)) {
                throw ValidationException::withMessages([
                    'paid_amount' => 'The amount entered creates a payment type that is not allowed for this transaction head.',
                ]);
            }

            $rule = $this->ruleMatcher->match(
                (int) $user->company_id,
                $transactionType,
                $settlementType,
                $head,
            );
            $requiresMoney = $this->settlementService->requiresMoney($rule);
            $requiresParty = $this->settlementService->requiresParty($rule);
            $party = $requiresParty
                ? $this->partyResolver->resolveRequired(
                    (int) $user->company_id,
                    $head,
                    $rule,
                    $data['party_id'] ?? null,
                )
                : null;

            if ($requiresMoney && ! $moneyAccount) {
                throw ValidationException::withMessages([
                    'money_account_id' => TransactionTypes::moneyLabel($transactionType).' is required for this payment type.',
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

            $sequence = $this->voucherNumberService->lock((int) $user->company_id, $transactionType);

            $existing = Transaction::query()
                ->where('company_id', $user->company_id)
                ->where('request_token', $data['request_token'])
                ->lockForUpdate()
                ->first();

            if ($existing) {
                return $this->matchingExistingTransaction($existing, $head, $transactionType);
            }

            $voucherNo = $this->voucherNumberService->issue($sequence);
            $now = now();
            $description = filled($data['description'] ?? null)
                ? (string) $data['description']
                : $head->name;

            $transaction = Transaction::query()->create([
                'uuid' => (string) Str::uuid(),
                'company_id' => $user->company_id,
                'transaction_head_id' => $head->id,
                'money_account_id' => $requiresMoney ? $moneyAccount?->id : null,
                'party_id' => $requiresParty ? $party?->id : null,
                'created_by' => $user->id,
                'voucher_no' => $voucherNo,
                'category' => $transactionType,
                'selling_type' => SaleSellingTypes::isSaleCategory($transactionType)
                    ? ($data['selling_type'] ?? null)
                    : null,
                'tracking_unit_id' => SaleSellingTypes::isSaleCategory($transactionType)
                    && filled($data['tracking_unit_id'] ?? null)
                        ? ($data['tracking_unit_id'] ?? null)
                        : null,
                'transaction_date' => $data['transaction_date'],
                'amount' => $amount,
                'settlement_type' => $settlement['settlement_type'],
                'paid_amount' => $settlement['paid_amount'],
                'due_amount' => $settlement['due_amount'],
                'due_date' => $settlement['due_date'],
                'reference' => $data['reference'] ?? null,
                'description' => $data['description'] ?? null,
                'request_token' => $data['request_token'],
                'status' => 'posted',
                'posted_at' => $now,
            ]);

            $journalEntry = JournalEntry::query()->create([
                'uuid' => (string) Str::uuid(),
                'company_id' => $user->company_id,
                'transaction_id' => $transaction->id,
                'posted_by' => $user->id,
                'voucher_no' => $voucherNo,
                'entry_date' => $data['transaction_date'],
                'narration' => $description,
                'status' => 'posted',
                'posted_at' => $now,
            ]);

            foreach ($lines as $index => $line) {
                $journalEntry->lines()->create([
                    'company_id' => $user->company_id,
                    'chart_of_account_id' => $line['account']->id,
                    'money_account_id' => $line['source'] === AccountingRule::SOURCE_SELECTED_MONEY
                        ? $moneyAccount?->id
                        : null,
                    'party_id' => in_array($line['source'], [
                        AccountingRule::SOURCE_PARTY_RECEIVABLE,
                        AccountingRule::SOURCE_PARTY_PAYABLE,
                    ], true) ? $party?->id : null,
                    'sequence' => $index + 1,
                    'description' => $description,
                    'debit' => $line['debit'],
                    'credit' => $line['credit'],
                ]);
            }

            $this->salesInvoiceService->syncForTransaction($transaction, $company);

            return $transaction->load([
                'transactionHead', 'moneyAccount', 'party',
                'journalEntry.lines.chartOfAccount', 'salesInvoice',
            ]);
        }, attempts: 5);
    }

    private function matchingExistingTransaction(
        Transaction $transaction,
        TransactionHead $head,
        string $transactionType,
    ): Transaction {
        if (
            (int) $transaction->transaction_head_id !== (int) $head->id
            || strcasecmp((string) $transaction->category, $transactionType) !== 0
            || $transaction->status !== 'posted'
        ) {
            throw ValidationException::withMessages([
                'request_token' => 'This request token has already been used by another transaction. Reload the form and submit again.',
            ]);
        }

        return $transaction->load([
            'transactionHead', 'moneyAccount', 'party',
            'journalEntry.lines.chartOfAccount', 'salesInvoice',
        ]);
    }

    private function validateHeadNature(TransactionHead $head, string $transactionType): void
    {
        $allowedTypes = TransactionTypes::postingTypes($transactionType);

        if ($allowedTypes !== [] && ! in_array($head->postingAccount?->type, $allowedTypes, true)) {
            throw ValidationException::withMessages([
                'transaction_head_id' => 'The selected head is linked to an unsuitable account type for this transaction.',
            ]);
        }
    }
}
