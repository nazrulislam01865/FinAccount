<?php

namespace App\Services\Accounting;

use App\Models\PaymentReceipt;
use App\Support\TransactionTypes;

class PaymentReceiptPdfService
{
    public function __construct(
        private readonly UnifiedDocumentPdfService $renderer,
    ) {}

    public function render(PaymentReceipt $receipt): string
    {
        $receipt->loadMissing([
            'transaction.transactionHead',
            'transaction.moneyAccount',
            'transaction.payments.moneyAccount',
            'transaction.party',
            'transaction.creator',
            'company',
            'party',
        ]);

        $company = $this->companyData($receipt);
        $party = $this->partyData($receipt);
        $transaction = $receipt->transaction;
        $isCollection = $transaction?->category === TransactionTypes::CUSTOMER_COLLECTION
            || $receipt->due_type === 'Receivable';
        $amount = (float) $receipt->amount;
        $currency = strtoupper((string) ($company['currency_code'] ?: 'TK'));
        $currency = $currency === 'BDT' ? 'TK' : $currency;
        $receiptDate = $receipt->receipt_date?->format('M d, Y') ?: '-';

        $paymentNames = $transaction?->payments?->pluck('moneyAccount.name')->filter()->unique()->values() ?? collect();
        $paymentMethod = $paymentNames->isNotEmpty()
            ? $paymentNames->implode(', ')
            : ($transaction?->moneyAccount?->name ?: '-');

        $reference = (string) ($transaction?->reference ?: ($transaction?->voucher_no ?: '-'));
        $purpose = (string) ($transaction?->description ?: ($isCollection ? 'Customer due collected' : 'Supplier due paid'));
        $remarks = trim(implode(' | ', array_filter([
            $transaction?->displayHeadName('-'),
            $receipt->previous_due_amount !== null ? 'Previous Due: '.number_format((float) $receipt->previous_due_amount, 2) : null,
            $receipt->remaining_due_amount !== null ? 'Remaining Due: '.number_format((float) $receipt->remaining_due_amount, 2) : null,
        ]))) ?: '-';

        return $this->renderer->render([
            'title' => 'MONEY RECEIPT',
            'company' => $company,
            'meta' => [
                ['label' => 'Receipt No.', 'value' => $receipt->receipt_no],
                ['label' => 'Receipt Date', 'value' => $receiptDate],
                ['label' => 'Payment Method', 'value' => $paymentMethod],
                ['label' => 'Reference No.', 'value' => $reference],
            ],
            'party_title' => $isCollection ? 'RECEIVED FROM' : 'PAID TO',
            'party' => $party,
            'purpose' => $purpose,
            'lines' => [[
                'description' => $isCollection ? 'Due collection received from customer' : 'Due payment made to supplier',
                'remarks' => $remarks,
                'amount' => $amount,
            ]],
            'currency_label' => $currency,
            'summary' => [
                ['label' => 'Subtotal', 'amount' => $amount],
                ['label' => 'Commission', 'amount' => 0],
                ['label' => 'Discount', 'amount' => 0],
                ['label' => 'VAT / Tax (0%)', 'amount' => 0],
                ['label' => 'TOTAL '.($isCollection ? 'RECEIVED' : 'PAID'), 'amount' => $amount, 'total' => true],
            ],
            'amount_words' => $this->amountWords($amount),
            'notes' => 'Thank you for your business.',
            'prepared_name' => (string) ($transaction?->creator?->name ?: 'System User'),
            'prepared_position' => 'Accounts Executive',
            'prepared_date' => $receiptDate,
            'prepared_email' => (string) ($transaction?->creator?->email ?: ($company['email'] ?? '')),
            'footer' => 'This receipt is electronically generated and may not require a physical signature.',
        ]);
    }

    /** @return array<string,mixed> */
    private function companyData(PaymentReceipt $receipt): array
    {
        $snapshot = is_array($receipt->company_snapshot) ? $receipt->company_snapshot : [];
        $company = $receipt->company;
        $name = trim((string) ($company?->name ?: ($snapshot['name'] ?? 'Bashir Agro')));
        if ($name === '' || str_contains(strtolower($name), 'hisebghor demo')) {
            $name = 'Bashir Agro';
        }

        return [
            'name' => $name,
            'short_name' => (string) ($company?->short_name ?: ($snapshot['short_name'] ?? 'BA')),
            'address' => (string) ($company?->address ?: ($snapshot['address'] ?? '')),
            'phone' => (string) ($company?->contact_phone ?: ($snapshot['phone'] ?? '')),
            'email' => (string) ($company?->contact_email ?: ($snapshot['email'] ?? '')),
            'website' => (string) ($company?->website ?: ($snapshot['website'] ?? 'www.Bashiragro.com')),
            'logo_path' => $company?->logo_path ?: ($snapshot['logo_path'] ?? null),
            'currency_code' => (string) ($company?->currency_code ?: ($snapshot['currency_code'] ?? 'TK')),
        ];
    }

    /** @return array<string,mixed> */
    private function partyData(PaymentReceipt $receipt): array
    {
        $snapshot = is_array($receipt->party_snapshot) ? $receipt->party_snapshot : [];
        $party = $receipt->party;

        return [
            'name' => (string) ($party?->name ?: ($snapshot['name'] ?? 'Party')),
            'address' => (string) ($party?->address ?: ($snapshot['address'] ?? '')),
            'phone' => (string) ($party?->phone ?: ($snapshot['phone'] ?? '')),
            'email' => (string) ($party?->email ?: ($snapshot['email'] ?? '')),
        ];
    }

    private function amountWords(float $amount): string
    {
        $whole = (int) floor($amount);
        $fraction = (int) round(($amount - $whole) * 100);
        $words = $this->numberToWords($whole).' Taka';
        if ($fraction > 0) {
            $words .= ' and '.$this->numberToWords($fraction).' Paisa';
        }

        return $words.' Only';
    }

    private function numberToWords(int $number): string
    {
        $dictionary = [
            0=>'Zero',1=>'One',2=>'Two',3=>'Three',4=>'Four',5=>'Five',6=>'Six',7=>'Seven',8=>'Eight',9=>'Nine',
            10=>'Ten',11=>'Eleven',12=>'Twelve',13=>'Thirteen',14=>'Fourteen',15=>'Fifteen',16=>'Sixteen',17=>'Seventeen',18=>'Eighteen',19=>'Nineteen',
            20=>'Twenty',30=>'Thirty',40=>'Forty',50=>'Fifty',60=>'Sixty',70=>'Seventy',80=>'Eighty',90=>'Ninety',
        ];
        if ($number < 0) return 'Negative '.$this->numberToWords(abs($number));
        if ($number < 21) return $dictionary[$number];
        if ($number < 100) {
            $tens = ((int) floor($number / 10)) * 10;
            return $dictionary[$tens].($number % 10 ? ' '.$dictionary[$number % 10] : '');
        }
        if ($number < 1000) return $dictionary[(int) floor($number / 100)].' Hundred'.($number % 100 ? ' '.$this->numberToWords($number % 100) : '');
        foreach ([10000000=>'Crore',100000=>'Lakh',1000=>'Thousand'] as $base=>$label) {
            if ($number >= $base) return $this->numberToWords((int) floor($number / $base)).' '.$label.($number % $base ? ' '.$this->numberToWords($number % $base) : '');
        }

        return (string) $number;
    }
}
