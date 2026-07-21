<?php

namespace App\Services\Accounting;

use App\Models\SalesInvoice;
use App\Support\TransactionTypes;

class SalesInvoicePdfService
{
    public function __construct(
        private readonly UnifiedDocumentPdfService $renderer,
    ) {}

    public function render(SalesInvoice $invoice): string
    {
        $invoice->loadMissing([
            'transaction.transactionHead',
            'transaction.moneyAccount',
            'transaction.payments.moneyAccount',
            'transaction.party',
            'transaction.creator',
            'transaction.saleLines',
            'transaction.feedDocument.lines.item',
            'transaction.feedDocument.warehouse',
            'company',
            'party',
        ]);

        $transaction = $invoice->transaction;
        $company = $this->companyData($invoice);
        $party = $this->partyData($invoice);
        $isPurchase = in_array($transaction?->category, [TransactionTypes::PURCHASE, TransactionTypes::ASSET_PURCHASE], true);
        $feedDocument = $transaction?->feedDocument;
        $currency = strtoupper((string) ($company['currency_code'] ?: 'TK'));
        $currency = $currency === 'BDT' ? 'TK' : $currency;
        $invoiceDate = $invoice->invoice_date?->format('M d, Y') ?: '-';
        $reference = (string) ($transaction?->reference ?: ($transaction?->voucher_no ?: '-'));

        $paymentNames = $transaction?->payments?->pluck('moneyAccount.name')->filter()->unique()->values() ?? collect();
        $paymentMethod = $paymentNames->isNotEmpty()
            ? $paymentNames->implode(', ')
            : ($transaction?->moneyAccount?->name
                ?: (strtoupper((string) $transaction?->settlement_type) === TransactionTypes::CREDIT ? 'Due / Credit' : '-'));

        $lines = [];
        if ($feedDocument?->lines?->isNotEmpty()) {
            foreach ($feedDocument->lines as $line) {
                $qty = rtrim(rtrim(number_format((float) $line->quantity, 4, '.', ''), '0'), '.');
                $lines[] = [
                    'description' => (string) ($line->item?->name ?: ($isPurchase ? 'Feed purchase' : 'Feed sale')),
                    'remarks' => 'Qty: '.$qty.' '.$line->unit.' | Rate: '.number_format((float) $line->rate, 2),
                    'amount' => (float) $line->line_total,
                ];
            }
        } elseif ($transaction?->saleLines?->isNotEmpty()) {
            foreach ($transaction->saleLines as $line) {
                $qty = $line->quantity === null ? null : rtrim(rtrim(number_format((float) $line->quantity, 4, '.', ''), '0'), '.');
                $remarks = trim(implode(' | ', array_filter([
                    $qty !== null ? 'Qty: '.$qty : null,
                    $line->unit ? 'Unit: '.$line->unit : null,
                    $line->rate !== null ? 'Rate: '.number_format((float) $line->rate, 2) : null,
                ])));
                $lines[] = [
                    'description' => (string) ($line->item_name ?: ($isPurchase ? 'Purchase' : 'Sale')),
                    'remarks' => $remarks ?: '-',
                    'amount' => (float) $line->line_total,
                ];
            }
        }
        if ($lines === []) {
            $lines[] = [
                'description' => (string) ($transaction?->displayHeadName($isPurchase ? 'Purchase' : 'Sale') ?: ($isPurchase ? 'Purchase' : 'Sale')),
                'remarks' => (string) ($transaction?->description ?: ($isPurchase ? 'Purchase transaction' : 'Sales transaction')),
                'amount' => (float) $invoice->subtotal,
            ];
        }

        $summary = [];
        if ($feedDocument) {
            $summary[] = ['label' => 'Subtotal', 'amount' => (float) $feedDocument->subtotal];
            $commission = (float) $feedDocument->overall_discount;
            $summary[] = ['label' => 'Commission (-)', 'display' => '(-) '.number_format($commission, 2), 'amount' => $commission];
            $transport = (float) $feedDocument->transport_cost;
            $summary[] = [
                'label' => $isPurchase ? 'Transportation (-)' : 'Transportation (+)',
                'display' => ($isPurchase ? '(-) ' : '(+) ').number_format($transport, 2),
                'amount' => $transport,
            ];
            $summary[] = ['label' => 'Other Cost', 'amount' => (float) $feedDocument->other_cost];
        } else {
            $summary[] = ['label' => 'Subtotal', 'amount' => (float) $invoice->subtotal];
            $summary[] = ['label' => 'Discount', 'amount' => (float) $invoice->discount_amount];
            $summary[] = ['label' => 'VAT / Tax', 'amount' => (float) $invoice->tax_amount];
        }
        $summary[] = ['label' => 'TOTAL '.($isPurchase ? 'PURCHASE' : 'SALE'), 'amount' => (float) $invoice->total_amount, 'total' => true];
        $summary[] = ['label' => 'Paid', 'amount' => (float) $invoice->paid_amount];
        $summary[] = ['label' => 'Due', 'amount' => (float) $invoice->due_amount];

        $documentTitle = strtoupper(trim((string) ($invoice->title ?: ($isPurchase ? 'Purchase Invoice' : 'Sales Invoice'))));
        if ($feedDocument) {
            $documentTitle = $isPurchase ? 'FEED PURCHASE INVOICE' : 'FEED SALES INVOICE';
        }

        return $this->renderer->render([
            'title' => $documentTitle,
            'company' => $company,
            'meta' => [
                ['label' => 'Invoice No.', 'value' => $invoice->invoice_no],
                ['label' => 'Invoice Date', 'value' => $invoiceDate],
                ['label' => 'Payment Method', 'value' => $paymentMethod],
                ['label' => 'Reference No.', 'value' => $reference],
            ],
            'party_title' => $isPurchase ? 'SUPPLIER / PAID TO' : 'CUSTOMER / RECEIVED FROM',
            'party' => $party,
            'purpose' => (string) ($transaction?->description ?: ($isPurchase ? 'Purchase transaction' : 'Sales transaction')),
            'lines' => $lines,
            'currency_label' => $currency,
            'summary' => $summary,
            'amount_words' => $this->amountWords((float) $invoice->total_amount),
            'notes' => $feedDocument && $isPurchase
                ? 'Transportation cost and commission are deductions from the feed purchase total.'
                : 'Thank you for your business.',
            'prepared_name' => (string) ($transaction?->creator?->name ?: 'System User'),
            'prepared_position' => 'Accounts Executive',
            'prepared_date' => $invoiceDate,
            'prepared_email' => (string) ($transaction?->creator?->email ?: ($company['email'] ?? '')),
            'footer' => 'This invoice is electronically generated and may not require a physical signature.',
        ]);
    }

    /** @return array<string,mixed> */
    private function companyData(SalesInvoice $invoice): array
    {
        $snapshot = is_array($invoice->company_snapshot) ? $invoice->company_snapshot : [];
        $company = $invoice->company;
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
    private function partyData(SalesInvoice $invoice): array
    {
        $snapshot = is_array($invoice->customer_snapshot) ? $invoice->customer_snapshot : [];
        $party = $invoice->party ?: $invoice->transaction?->party;

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
