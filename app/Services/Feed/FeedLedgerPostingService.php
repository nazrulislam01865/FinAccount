<?php

namespace App\Services\Feed;

use App\Models\ChartOfAccount;
use App\Models\Company;
use App\Models\Feed\FeedSetting;
use App\Models\JournalEntry;
use App\Models\MoneyAccount;
use App\Models\Party;
use App\Models\Transaction;
use App\Models\TransactionHead;
use App\Models\User;
use App\Services\Accounting\DecimalAmount;
use App\Services\Accounting\SalesInvoiceService;
use App\Services\Accounting\TransactionSettlementService;
use App\Services\Accounting\VoucherNumberService;
use App\Services\Company\CompanyAccountingPeriodService;
use App\Support\TransactionTypes;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class FeedLedgerPostingService
{
    public function __construct(
        private readonly VoucherNumberService $voucherNumberService,
        private readonly DecimalAmount $decimalAmount,
        private readonly TransactionSettlementService $settlementService,
        private readonly SalesInvoiceService $salesInvoiceService,
        private readonly CompanyAccountingPeriodService $accountingPeriodService,
    ) {}

    /** @param array<string, mixed> $data */
    public function postPurchase(array $data, User $user, FeedSetting $settings): Transaction
    {
        return $this->post($data, $user, $settings, TransactionTypes::PURCHASE);
    }

    /** @param array<string, mixed> $data */
    public function postSale(array $data, User $user, FeedSetting $settings): Transaction
    {
        return $this->post($data, $user, $settings, TransactionTypes::SALE);
    }

    /** @param array<string, mixed> $data */
    private function post(
        array $data,
        User $user,
        FeedSetting $settings,
        string $category,
    ): Transaction {
        if (! $user->company_id) {
            throw ValidationException::withMessages([
                'company' => 'Your user account is not connected to a company.',
            ]);
        }

        return DB::transaction(function () use ($data, $user, $settings, $category): Transaction {
            $company = Company::query()
                ->with(['defaultFinancialYear', 'currency', 'timeZone'])
                ->lockForUpdate()
                ->findOrFail($user->company_id);

            $this->accountingPeriodService->assertPostingAllowed($company, (string) $data['transaction_date']);

            $head = $this->head($settings, (int) $company->id, $category);

            $existing = Transaction::query()
                ->where('company_id', $user->company_id)
                ->where('request_token', $data['request_token'])
                ->first();

            if ($existing) {
                return $this->matchingExistingTransaction($existing, $head, $category);
            }

            $postingAccount = $this->postingAccount($head, (int) $company->id, $category);
            $party = $this->party(
                (int) $company->id,
                (int) $data['party_id'],
                $category === TransactionTypes::SALE ? 'Customer' : 'Supplier',
            );

            $scale = (int) ($company->currency?->decimal_places ?? 2);
            $amount = $this->decimalAmount->normalize($data['amount'], $scale);
            $settlement = $this->settlementService->prepare($amount, $data, $scale);
            $paid = (float) $settlement['paid_amount'];
            $due = (float) $settlement['due_amount'];
            $moneyAccount = $paid > 0
                ? $this->moneyAccount((int) $company->id, $data['money_account_id'] ?? null)
                : null;

            if ($due > 0) {
                $this->assertPartyControlAccount($party, $category, (int) $company->id);
            }

            $sequence = $this->voucherNumberService->lock((int) $company->id, $category);

            $existing = Transaction::query()
                ->where('company_id', $user->company_id)
                ->where('request_token', $data['request_token'])
                ->lockForUpdate()
                ->first();

            if ($existing) {
                return $this->matchingExistingTransaction($existing, $head, $category);
            }

            $voucherNo = $this->voucherNumberService->issue($sequence);
            $now = now();
            $description = filled($data['description'] ?? null)
                ? (string) $data['description']
                : ($category === TransactionTypes::SALE ? 'Feed sale' : 'Feed purchase');

            $transaction = Transaction::query()->create([
                'uuid' => (string) Str::uuid(),
                'company_id' => $company->id,
                'transaction_head_id' => $head->id,
                'money_account_id' => $moneyAccount?->id,
                'party_id' => $party->id,
                'created_by' => $user->id,
                'voucher_no' => $voucherNo,
                'category' => $category,
                'transaction_date' => $data['transaction_date'],
                'amount' => $amount,
                'settlement_type' => $settlement['settlement_type'],
                'paid_amount' => $settlement['paid_amount'],
                'due_amount' => $settlement['due_amount'],
                'due_date' => $settlement['due_date'],
                'reference' => $data['reference'] ?? null,
                'description' => $description,
                'request_token' => $data['request_token'],
                'status' => 'posted',
                'posted_at' => $now,
            ]);

            $journalEntry = JournalEntry::query()->create([
                'uuid' => (string) Str::uuid(),
                'company_id' => $company->id,
                'transaction_id' => $transaction->id,
                'posted_by' => $user->id,
                'voucher_no' => $voucherNo,
                'entry_date' => $data['transaction_date'],
                'narration' => $description,
                'status' => 'posted',
                'posted_at' => $now,
            ]);

            $lines = $category === TransactionTypes::SALE
                ? $this->saleLines($postingAccount, $party, $moneyAccount, $amount, $settlement)
                : $this->purchaseLines($postingAccount, $party, $moneyAccount, $amount, $settlement);

            $this->assertBalanced($lines);
            $this->assertNoAccountOnBothSides($lines);

            foreach ($lines as $index => $line) {
                $journalEntry->lines()->create([
                    'company_id' => $company->id,
                    'chart_of_account_id' => $line['account']->id,
                    'money_account_id' => $line['money_account_id'],
                    'party_id' => $line['party_id'],
                    'sequence' => $index + 1,
                    'description' => $description,
                    'debit' => $line['debit'],
                    'credit' => $line['credit'],
                ]);
            }

            if ($category === TransactionTypes::SALE) {
                $this->salesInvoiceService->syncForTransaction($transaction, $company);
            }

            return $transaction->load([
                'transactionHead', 'moneyAccount', 'party',
                'journalEntry.lines.chartOfAccount', 'salesInvoice',
            ]);
        }, attempts: 5);
    }

    private function matchingExistingTransaction(
        Transaction $transaction,
        TransactionHead $head,
        string $category,
    ): Transaction {
        if (
            (int) $transaction->transaction_head_id !== (int) $head->id
            || strcasecmp((string) $transaction->category, $category) !== 0
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

    private function head(FeedSetting $settings, int $companyId, string $category): TransactionHead
    {
        $head = $category === TransactionTypes::SALE
            ? $settings->saleTransactionHead
            : $settings->purchaseTransactionHead;

        if (
            ! $head
            || (int) $head->company_id !== $companyId
            || ! $head->is_active
            || ! str_starts_with(strtoupper((string) $head->code), 'SYS-FEED-')
            || strcasecmp((string) $head->category, $category) !== 0
        ) {
            throw ValidationException::withMessages([
                'feed_setup' => 'The automatic feed posting head is unavailable. Reload the page and try again.',
            ]);
        }

        return $head;
    }

    private function postingAccount(TransactionHead $head, int $companyId, string $category): ChartOfAccount
    {
        $account = $head->postingAccount;
        $expectedType = $category === TransactionTypes::SALE ? 'Income' : 'Asset';

        if (
            ! $account
            || (int) $account->company_id !== $companyId
            || ! $account->is_active
            || $account->type !== $expectedType
            || (int) $account->level !== 3
        ) {
            throw ValidationException::withMessages([
                'feed_setup' => 'The automatic feed posting ledger is unavailable. Reload the page and try again.',
            ]);
        }

        return $account;
    }

    private function party(int $companyId, int $partyId, string $type): Party
    {
        $party = Party::query()
            ->with(['receivableAccount', 'payableAccount'])
            ->where('company_id', $companyId)
            ->where('type', $type)
            ->where('is_active', true)
            ->find($partyId);

        if (! $party) {
            throw ValidationException::withMessages([
                'party_id' => 'Select a valid active '.$type.' for this feed transaction.',
            ]);
        }

        return $party;
    }

    private function moneyAccount(int $companyId, mixed $moneyAccountId): MoneyAccount
    {
        if (! filled($moneyAccountId)) {
            throw ValidationException::withMessages([
                'money_account_id' => 'Select a Cash, Bank, or Mobile Account when an amount is paid or received now.',
            ]);
        }

        $moneyAccount = MoneyAccount::query()
            ->with('chartOfAccount')
            ->where('company_id', $companyId)
            ->where('is_active', true)
            ->whereHas('chartOfAccount', fn ($query) => $query
                ->where('company_id', $companyId)
                ->where('is_active', true)
                ->where('level', 3))
            ->find($moneyAccountId);

        if (! $moneyAccount || ! $moneyAccount->chartOfAccount) {
            throw ValidationException::withMessages([
                'money_account_id' => 'Select a valid active Cash, Bank, or Mobile Account.',
            ]);
        }

        return $moneyAccount;
    }

    private function assertPartyControlAccount(Party $party, string $category, int $companyId): void
    {
        $account = $category === TransactionTypes::SALE
            ? $party->receivableAccount
            : $party->payableAccount;
        $expectedType = $category === TransactionTypes::SALE ? 'Asset' : 'Liability';
        $field = $category === TransactionTypes::SALE ? 'receivable' : 'payable';

        if (
            ! $account
            || (int) $account->company_id !== $companyId
            || ! $account->is_active
            || $account->type !== $expectedType
            || (int) $account->level !== 3
        ) {
            throw ValidationException::withMessages([
                'party_id' => $party->name.' needs an active level-3 '.$field.' ledger before posting a due or partial feed transaction.',
            ]);
        }
    }

    /**
     * @param array{settlement_type:string,paid_amount:string,due_amount:string,due_date:?string} $settlement
     * @return array<int, array{account:ChartOfAccount,money_account_id:?int,party_id:?int,debit:string,credit:string}>
     */
    private function purchaseLines(
        ChartOfAccount $inventoryAccount,
        Party $supplier,
        ?MoneyAccount $moneyAccount,
        string $amount,
        array $settlement,
    ): array {
        $lines = [[
            'account' => $inventoryAccount,
            'money_account_id' => null,
            'party_id' => null,
            'debit' => $amount,
            'credit' => '0.00',
        ]];

        if ((float) $settlement['paid_amount'] > 0 && $moneyAccount?->chartOfAccount) {
            $lines[] = [
                'account' => $moneyAccount->chartOfAccount,
                'money_account_id' => $moneyAccount->id,
                'party_id' => null,
                'debit' => '0.00',
                'credit' => $settlement['paid_amount'],
            ];
        }

        if ((float) $settlement['due_amount'] > 0 && $supplier->payableAccount) {
            $lines[] = [
                'account' => $supplier->payableAccount,
                'money_account_id' => null,
                'party_id' => $supplier->id,
                'debit' => '0.00',
                'credit' => $settlement['due_amount'],
            ];
        }

        return $lines;
    }

    /**
     * @param array{settlement_type:string,paid_amount:string,due_amount:string,due_date:?string} $settlement
     * @return array<int, array{account:ChartOfAccount,money_account_id:?int,party_id:?int,debit:string,credit:string}>
     */
    private function saleLines(
        ChartOfAccount $salesAccount,
        Party $customer,
        ?MoneyAccount $moneyAccount,
        string $amount,
        array $settlement,
    ): array {
        $lines = [];

        if ((float) $settlement['paid_amount'] > 0 && $moneyAccount?->chartOfAccount) {
            $lines[] = [
                'account' => $moneyAccount->chartOfAccount,
                'money_account_id' => $moneyAccount->id,
                'party_id' => null,
                'debit' => $settlement['paid_amount'],
                'credit' => '0.00',
            ];
        }

        if ((float) $settlement['due_amount'] > 0 && $customer->receivableAccount) {
            $lines[] = [
                'account' => $customer->receivableAccount,
                'money_account_id' => null,
                'party_id' => $customer->id,
                'debit' => $settlement['due_amount'],
                'credit' => '0.00',
            ];
        }

        $lines[] = [
            'account' => $salesAccount,
            'money_account_id' => null,
            'party_id' => null,
            'debit' => '0.00',
            'credit' => $amount,
        ];

        return $lines;
    }

    /** @param array<int, array{debit:string,credit:string}> $lines */
    private function assertBalanced(array $lines): void
    {
        $debits = round(array_sum(array_map(fn (array $line): float => (float) $line['debit'], $lines)), 2);
        $credits = round(array_sum(array_map(fn (array $line): float => (float) $line['credit'], $lines)), 2);

        if ($debits !== $credits) {
            throw ValidationException::withMessages([
                'feed_setup' => 'The direct feed ledger entry is not balanced and was not posted.',
            ]);
        }
    }

    /** @param array<int, array{account:ChartOfAccount,debit:string,credit:string}> $lines */
    private function assertNoAccountOnBothSides(array $lines): void
    {
        $debitIds = collect($lines)
            ->filter(fn (array $line): bool => (float) $line['debit'] > 0)
            ->map(fn (array $line): int => (int) $line['account']->id)
            ->all();
        $creditIds = collect($lines)
            ->filter(fn (array $line): bool => (float) $line['credit'] > 0)
            ->map(fn (array $line): int => (int) $line['account']->id)
            ->all();

        if (array_intersect($debitIds, $creditIds) !== []) {
            throw ValidationException::withMessages([
                'feed_setup' => 'A feed ledger cannot be used on both debit and credit sides of the same posting.',
            ]);
        }
    }
}
