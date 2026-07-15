<?php

namespace App\Services\Accounting;

use App\Models\Company;
use App\Models\SalesInvoice;
use App\Models\Transaction;
use App\Support\TransactionTypes;
use Illuminate\Support\Str;

class SalesInvoiceService
{
    public function syncForTransaction(Transaction $transaction, Company $company): ?SalesInvoice
    {
        $transaction->loadMissing([
            'transactionHead',
            'moneyAccount',
            'party',
            'salesInvoice',
        ]);

        if (! $this->shouldGenerate($transaction)) {
            $transaction->salesInvoice?->delete();

            return null;
        }

        [$paidAmount, $dueAmount] = $this->paidAndDueAmounts($transaction);
        $totalAmount = $this->money($transaction->amount);
        $status = $this->status($paidAmount, $dueAmount);
        $invoiceNo = $this->invoiceNo($transaction);

        $payload = [
            'company_id' => $transaction->company_id,
            'transaction_id' => $transaction->id,
            'party_id' => $transaction->party_id,
            'invoice_no' => $invoiceNo,
            'title' => 'Sales Invoice',
            'invoice_date' => $transaction->transaction_date,
            'due_date' => $transaction->due_date,
            'subtotal' => $totalAmount,
            'discount_amount' => '0.00',
            'tax_amount' => '0.00',
            'total_amount' => $totalAmount,
            'paid_amount' => $paidAmount,
            'due_amount' => $dueAmount,
            'status' => $status,
            'customer_snapshot' => $this->customerSnapshot($transaction),
            'company_snapshot' => $this->companySnapshot($company),
        ];

        $invoice = $transaction->salesInvoice;

        if ($invoice) {
            $invoice->update($payload);

            return $invoice->refresh();
        }

        return SalesInvoice::query()->create([
            'uuid' => (string) Str::uuid(),
            ...$payload,
        ]);
    }

    public function shouldGenerate(Transaction $transaction): bool
    {
        return $transaction->category === TransactionTypes::SALE
            && $transaction->status === 'posted';
    }

    /** @return array{0:string,1:string} */
    private function paidAndDueAmounts(Transaction $transaction): array
    {
        $total = $this->money($transaction->amount);
        $settlement = strtoupper((string) ($transaction->settlement_type ?: TransactionTypes::CASH));

        return match ($settlement) {
            TransactionTypes::CREDIT => ['0.00', $total],
            TransactionTypes::PARTIAL => [
                $this->money($transaction->paid_amount ?? 0),
                $this->money($transaction->due_amount ?? 0),
            ],
            default => [$total, '0.00'],
        };
    }

    private function status(string $paidAmount, string $dueAmount): string
    {
        $paid = (float) $paidAmount;
        $due = (float) $dueAmount;

        return match (true) {
            $due <= 0.0 => SalesInvoice::STATUS_PAID,
            $paid <= 0.0 => SalesInvoice::STATUS_UNPAID,
            default => SalesInvoice::STATUS_PARTIAL,
        };
    }

    private function invoiceNo(Transaction $transaction): string
    {
        $voucherNo = preg_replace('/[^A-Za-z0-9\-]/', '-', (string) $transaction->voucher_no);

        return 'INV-'.$voucherNo;
    }

    /** @return array<string, mixed>|null */
    private function customerSnapshot(Transaction $transaction): ?array
    {
        if (! $transaction->party) {
            return [
                'name' => 'Cash Customer',
                'code' => null,
                'type' => null,
            ];
        }

        return [
            'name' => $transaction->party->name,
            'code' => $transaction->party->code,
            'type' => $transaction->party->type,
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
