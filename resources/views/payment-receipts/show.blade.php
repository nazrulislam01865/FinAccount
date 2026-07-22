@php
    use Illuminate\Support\Facades\Storage;

    $snapshotCompany = is_array($receipt->company_snapshot) ? $receipt->company_snapshot : [];
    $currentCompany = $receipt->company;
    $company = [
        'name' => $currentCompany?->name ?: ($snapshotCompany['name'] ?? 'Bashir Agro'),
        'short_name' => $currentCompany?->short_name ?: ($snapshotCompany['short_name'] ?? 'BA'),
        'address' => $currentCompany?->address ?: ($snapshotCompany['address'] ?? null),
        'phone' => $currentCompany?->contact_phone ?: ($snapshotCompany['phone'] ?? null),
        'email' => $currentCompany?->contact_email ?: ($snapshotCompany['email'] ?? null),
        'website' => $currentCompany?->website ?: ($snapshotCompany['website'] ?? 'www.Bashiragro.com'),
        'currency_code' => $currentCompany?->currency_code ?: ($snapshotCompany['currency_code'] ?? 'TK'),
        'logo_path' => $currentCompany?->logo_path ?: ($snapshotCompany['logo_path'] ?? null),
    ];


    $companyNameForReceipt = trim((string) ($company['name'] ?? ''));
    if ($companyNameForReceipt === '' || str_contains(strtolower($companyNameForReceipt), 'hisebghor demo')) {
        $company['name'] = 'Bashir Agro';
        $company['short_name'] = 'BA';
    }
    if (empty($company['website'])) {
        $company['website'] = 'www.Bashiragro.com';
    }
    $snapshotParty = is_array($receipt->party_snapshot) ? $receipt->party_snapshot : [];
    $party = [
        'name' => $receipt->party?->name ?: ($snapshotParty['name'] ?? 'Party'),
        'code' => $receipt->party?->code ?: ($snapshotParty['code'] ?? null),
        'type' => $receipt->party?->type ?: ($snapshotParty['type'] ?? null),
        'phone' => $receipt->party?->phone ?: ($snapshotParty['phone'] ?? null),
        'email' => $receipt->party?->email ?: ($snapshotParty['email'] ?? null),
        'address' => $receipt->party?->address ?: ($snapshotParty['address'] ?? null),
    ];

    $transaction = $receipt->transaction;
    $logoPath = $company['logo_path'] ?? null;
    $logoUrl = $logoPath ? Storage::disk('public')->url($logoPath) : null;
    $isCollection = $receipt->due_type === 'Receivable';
    $moneyAccount = $transaction?->moneyAccount?->name ?: '-';
    $receiptDate = $receipt->receipt_date?->format('M d, Y') ?: '-';
    $amount = (float) $receipt->amount;
    $previousDue = $receipt->previous_due_amount === null ? null : (float) $receipt->previous_due_amount;
    $remainingDue = $receipt->remaining_due_amount === null ? null : (float) $receipt->remaining_due_amount;
    $descriptionTitle = $isCollection ? 'Due collection received from customer' : 'Due payment made to supplier';
    $purpose = $transaction?->description ?: ($isCollection ? 'Customer due collected' : 'Supplier due paid');
    $referenceNo = $transaction?->reference ?: ($transaction?->voucher_no ?: '-');
    $remarkLines = array_values(array_filter([
        $transaction?->displayHeadName($isCollection ? 'Customer Due Collection' : 'Supplier Due Payment'),
        $previousDue !== null ? 'Previous Due: '.number_format($previousDue, 2) : null,
        $remainingDue !== null ? 'Remaining Due: '.number_format($remainingDue, 2) : null,
    ], static fn ($value) => filled($value)));
    $remarks = $remarkLines !== [] ? implode("\n", $remarkLines) : '-';

    $currencyLabel = strtoupper((string) ($company['currency_code'] ?: 'TK'));
    $currencyLabel = $currencyLabel === 'BDT' ? 'TK' : $currencyLabel;
    $commission = 0.0;
    $discount = 0.0;
    $tax = 0.0;
    $total = $amount + $commission - $discount + $tax;
    $phoneEmail = trim(($party['phone'] ?: '').(($party['phone'] && $party['email']) ? ' / ' : '').($party['email'] ?: ''));
    $preparedUser = $transaction?->creator ?: auth()->user();
    $preparedByName = $preparedUser?->name ?: 'System User';
    $preparedByPosition = trim((string) ($preparedUser?->position ?? ''));

    $numberToWords = function (int $number) use (&$numberToWords): string {
        $dictionary = [
            0 => 'Zero', 1 => 'One', 2 => 'Two', 3 => 'Three', 4 => 'Four', 5 => 'Five', 6 => 'Six', 7 => 'Seven', 8 => 'Eight', 9 => 'Nine',
            10 => 'Ten', 11 => 'Eleven', 12 => 'Twelve', 13 => 'Thirteen', 14 => 'Fourteen', 15 => 'Fifteen', 16 => 'Sixteen', 17 => 'Seventeen', 18 => 'Eighteen', 19 => 'Nineteen',
            20 => 'Twenty', 30 => 'Thirty', 40 => 'Forty', 50 => 'Fifty', 60 => 'Sixty', 70 => 'Seventy', 80 => 'Eighty', 90 => 'Ninety',
        ];

        if ($number < 0) return 'Negative '.$numberToWords(abs($number));
        if ($number < 21) return $dictionary[$number];
        if ($number < 100) {
            $tens = ((int) floor($number / 10)) * 10;
            $unit = $number % 10;
            return $dictionary[$tens].($unit ? ' '.$dictionary[$unit] : '');
        }
        if ($number < 1000) return $dictionary[(int) floor($number / 100)].' Hundred'.($number % 100 ? ' '.$numberToWords($number % 100) : '');
        foreach ([10000000 => 'Crore', 100000 => 'Lakh', 1000 => 'Thousand'] as $base => $label) {
            if ($number >= $base) return $numberToWords((int) floor($number / $base)).' '.$label.($number % $base ? ' '.$numberToWords($number % $base) : '');
        }
        return (string) $number;
    };

    $decimalAmount = round(($amount - floor($amount)) * 100);
    $amountInWords = $numberToWords((int) floor($amount)).' Taka'.($decimalAmount > 0 ? ' and '.$numberToWords((int) $decimalAmount).' Paisa' : '').' Only';

    $metaRows = [
        ['label' => 'Receipt No.', 'value' => $receipt->receipt_no],
        ['label' => 'Receipt Date', 'value' => $receiptDate],
        ['label' => 'Payment Method', 'value' => $moneyAccount],
        ['label' => 'Reference No.', 'value' => $referenceNo],
    ];
    $documentLines = [[
        'description' => $descriptionTitle,
        'remarks' => $remarks,
        'remarks_lines' => $remarkLines,
        'amount' => $amount,
    ]];
    $summaryRows = [
        ['label' => 'Subtotal', 'amount' => $amount],
        ['label' => 'Commission', 'amount' => $commission],
        ['label' => 'Discount', 'amount' => $discount],
        ['label' => 'VAT / Tax (0%)', 'amount' => $tax],
        ['label' => 'TOTAL '.($isCollection ? 'RECEIVED' : 'PAID'), 'amount' => $total, 'total' => true],
    ];
@endphp


<x-layouts::accounting :title="$receipt->receipt_no">
    <div class="hg-page-header no-print">
        <div>
            <h1>Money Receipt - {{ $receipt->receipt_no }}</h1>
            <p>Printable one-page receipt generated from voucher {{ $transaction?->voucher_no }}.</p>
        </div>
        <div class="hg-actions">
            <a class="hg-btn" href="{{ route('transactions.index', ['category' => $transaction?->category]) }}">Back to Transactions</a>
            <a class="hg-btn hg-btn-soft" href="{{ route('payment-receipts.download', $receipt) }}">Download Receipt</a>
            <button class="hg-btn hg-btn-primary" type="button" onclick="window.print()">Print / Save PDF</button>
        </div>
    </div>

    @include('accounting.partials.unified-document', [
        'documentTitle' => 'MONEY RECEIPT',
        'company' => $company,
        'logoUrl' => $logoUrl,
        'metaRows' => $metaRows,
        'partySectionTitle' => $isCollection ? 'RECEIVED FROM' : 'PAID TO',
        'party' => $party,
        'partyPhoneEmail' => $phoneEmail,
        'purpose' => $purpose,
        'documentLines' => $documentLines,
        'currencyLabel' => $currencyLabel,
        'summaryRows' => $summaryRows,
        'amountInWords' => $amountInWords,
        'notes' => 'Thank you for your business.',
        'preparedByName' => $preparedByName,
        'preparedByPosition' => $preparedByPosition,
        'preparedDate' => $receiptDate,
    ])
</x-layouts::accounting>
