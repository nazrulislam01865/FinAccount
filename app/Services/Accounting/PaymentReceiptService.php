<?php

namespace App\Services\Accounting;

use App\Models\Company;
use App\Models\PaymentReceipt;
use App\Models\Transaction;
use App\Services\Accounting\Reports\FinancialReportService;
use App\Support\TransactionTypes;
use Illuminate\Support\Str;

class PaymentReceiptService
{
    public function __construct(
        private readonly FinancialReportService $reportService,
    ) {}

    public function syncForTransaction(Transaction $transaction, Company $company): ?PaymentReceipt
    {
        $transaction->loadMissing([
            'transactionHead',
            'moneyAccount',
            'party.receivableAccount',
            'party.payableAccount',
            'paymentReceipt',
        ]);

        if (! $this->shouldGenerate($transaction)) {
            $transaction->paymentReceipt?->delete();

            return null;
        }

        [$remainingDue, $previousDue] = $this->dueAmounts($transaction);
        $receiptNo = $this->receiptNo($transaction);
        $dueType = $this->dueType($transaction);

        $payload = [
            'company_id' => $transaction->company_id,
            'transaction_id' => $transaction->id,
            'party_id' => $transaction->party_id,
            'receipt_no' => $receiptNo,
            'title' => $this->title($transaction),
            'receipt_date' => $transaction->transaction_date,
            'due_type' => $dueType,
            'amount' => $this->money($transaction->amount),
            'previous_due_amount' => $previousDue === null ? null : $this->money($previousDue),
            'remaining_due_amount' => $remainingDue === null ? null : $this->money($remainingDue),
            'status' => PaymentReceipt::STATUS_ISSUED,
            'party_snapshot' => $this->partySnapshot($transaction),
            'company_snapshot' => $this->companySnapshot($company),
        ];

        $receipt = $transaction->paymentReceipt;

        if ($receipt) {
            $receipt->update($payload);

            return $receipt->refresh();
        }

        return PaymentReceipt::query()->create([
            'uuid' => (string) Str::uuid(),
            ...$payload,
        ]);
    }

    public function shouldGenerate(Transaction $transaction): bool
    {
        return $transaction->status === 'posted'
            && in_array($transaction->category, [
                TransactionTypes::CUSTOMER_COLLECTION,
                TransactionTypes::SUPPLIER_PAYMENT,
            ], true)
            && $transaction->party_id !== null;
    }

    private function title(Transaction $transaction): string
    {
        return $transaction->category === TransactionTypes::CUSTOMER_COLLECTION
            ? 'Due Collection Receipt'
            : 'Due Payment Voucher';
    }

    private function dueType(Transaction $transaction): string
    {
        return $transaction->category === TransactionTypes::CUSTOMER_COLLECTION
            ? 'Receivable'
            : 'Payable';
    }

    private function receiptNo(Transaction $transaction): string
    {
        $voucherNo = preg_replace('/[^A-Za-z0-9\-]/', '-', (string) $transaction->voucher_no);
        $prefix = $transaction->category === TransactionTypes::CUSTOMER_COLLECTION ? 'RCP' : 'PV';

        return $prefix.'-'.$voucherNo;
    }

    /** @return array{0:float|null,1:float|null} */
    private function dueAmounts(Transaction $transaction): array
    {
        $party = $transaction->party;
        if (! $party) {
            return [null, null];
        }

        $dueType = $this->dueType($transaction);
        $accountId = $dueType === 'Receivable'
            ? $party->receivable_account_id
            : $party->payable_account_id;

        if (! $accountId) {
            return [null, null];
        }

        $report = $this->reportService->dueReport((int) $transaction->company_id, [
            'as_of_date' => $transaction->transaction_date?->toDateString() ?: now()->toDateString(),
            'due_type' => strtolower($dueType),
            'include_zero_balances' => true,
        ]);

        $row = collect($report['rows'])->first(fn (array $item): bool =>
            (int) $item['party_id'] === (int) $party->id
            && (int) $item['account_id'] === (int) $accountId
            && $item['due_type'] === $dueType
        );

        if (! $row) {
            return [null, null];
        }

        $remaining = round(max((float) ($row['closing_balance'] ?? 0), 0), 2);
        $previous = round($remaining + (float) $transaction->amount, 2);

        return [$remaining, $previous];
    }

    /** @return array<string, mixed>|null */
    private function partySnapshot(Transaction $transaction): ?array
    {
        if (! $transaction->party) {
            return null;
        }

        return [
            'name' => $transaction->party->name,
            'code' => $transaction->party->code,
            'type' => $transaction->party->type,
            'phone' => $transaction->party->phone,
            'email' => $transaction->party->email,
            'address' => $transaction->party->address,
        ];
    }

    /** @return array<string, mixed> */
    private function companySnapshot(Company $company): array
    {
        return [
            'name' => $company->name,
            'short_name' => $company->short_name,
            'code' => $company->code,
            'address' => $company->address,
            'phone' => $company->contact_phone,
            'email' => $company->contact_email,
            'website' => $company->website,
            'tin' => $company->tin,
            'bin_vat_registration_no' => $company->bin_vat_registration_no,
            'logo_path' => $company->logo_path,
            'currency_code' => $company->currency_code,
        ];
    }

    private function money(mixed $value): string
    {
        return number_format((float) $value, 2, '.', '');
    }
}
