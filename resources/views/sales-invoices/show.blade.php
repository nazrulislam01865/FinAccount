@php
    use Illuminate\Support\Facades\Storage;
    use App\Support\TransactionTypes;

    $snapshotCompany = is_array($invoice->company_snapshot) ? $invoice->company_snapshot : [];
    $currentCompany = $invoice->company;
    $company = [
        'name' => $currentCompany?->name ?: ($snapshotCompany['name'] ?? 'Bashir Agro'),
        'short_name' => $currentCompany?->short_name ?: ($snapshotCompany['short_name'] ?? 'BA'),
        'address' => $currentCompany?->address ?: ($snapshotCompany['address'] ?? null),
        'phone' => $currentCompany?->contact_phone ?: ($snapshotCompany['phone'] ?? null),
        'email' => $currentCompany?->contact_email ?: ($snapshotCompany['email'] ?? null),
        'website' => $currentCompany?->website ?: ($snapshotCompany['website'] ?? 'www.Bashiragro.com'),
        'logo_path' => $currentCompany?->logo_path ?: ($snapshotCompany['logo_path'] ?? null),
        'currency_code' => $currentCompany?->currency_code ?: ($snapshotCompany['currency_code'] ?? 'TK'),
    ];
    $companyName = trim((string) ($company['name'] ?? ''));
    if ($companyName === '' || str_contains(strtolower($companyName), 'hisebghor demo')) {
        $company['name'] = 'Bashir Agro';
        $company['short_name'] = 'BA';
    }
    if (empty($company['website'])) $company['website'] = 'www.Bashiragro.com';

    $transaction = $invoice->transaction;
    $feedDocument = $transaction?->feedDocument;
    $isPurchase = in_array($transaction?->category, [TransactionTypes::PURCHASE, TransactionTypes::ASSET_PURCHASE], true);
    $partySnapshot = is_array($invoice->customer_snapshot) ? $invoice->customer_snapshot : [];
    $party = [
        'name' => $invoice->party?->name ?: $transaction?->party?->name ?: ($partySnapshot['name'] ?? ($isPurchase ? 'Supplier' : 'Cash Customer')),
        'phone' => $invoice->party?->phone ?: $transaction?->party?->phone ?: ($partySnapshot['phone'] ?? null),
        'email' => $invoice->party?->email ?: $transaction?->party?->email ?: ($partySnapshot['email'] ?? null),
        'address' => $invoice->party?->address ?: $transaction?->party?->address ?: ($partySnapshot['address'] ?? null),
    ];

    $logoUrl = !empty($company['logo_path']) ? Storage::disk('public')->url($company['logo_path']) : null;
    $currencyLabel = strtoupper((string) ($company['currency_code'] ?: 'TK'));
    $currencyLabel = $currencyLabel === 'BDT' ? 'TK' : $currencyLabel;
    $invoiceDate = $invoice->invoice_date?->format('M d, Y') ?: '-';
    $referenceNo = $transaction?->reference ?: ($transaction?->voucher_no ?: '-');
    $paymentNames = $transaction?->payments?->pluck('moneyAccount.name')->filter()->unique()->values() ?? collect();
    $paymentMethod = $paymentNames->isNotEmpty()
        ? $paymentNames->implode(', ')
        : ($transaction?->moneyAccount?->name ?? (($transaction?->settlement_type === TransactionTypes::CREDIT) ? 'Due / Credit' : '-'));
    $phoneEmail = trim(($party['phone'] ?: '').(($party['phone'] && $party['email']) ? ' / ' : '').($party['email'] ?: ''));
    $documentTitle = strtoupper((string) ($invoice->title ?: ($isPurchase ? 'Purchase Invoice' : 'Sales Invoice')));
    $purpose = $transaction?->description ?: ($isPurchase ? 'Purchase transaction' : 'Sales transaction');
    $preparedByName = auth()->user()?->name ?: 'System User';
    $preparedByEmail = auth()->user()?->email ?: ($company['email'] ?? '');

    $documentLines = [];
    if ($feedDocument?->lines?->isNotEmpty()) {
        foreach ($feedDocument->lines as $line) {
            $qty = rtrim(rtrim(number_format((float) $line->quantity, 4, '.', ''), '0'), '.');
            $documentLines[] = [
                'description' => $line->item?->name ?: ($isPurchase ? 'Feed purchase' : 'Feed sale'),
                'remarks' => 'Qty: '.$qty.' '.$line->unit.' | Rate: '.number_format((float) $line->rate, 2),
                'amount' => (float) $line->line_total,
            ];
        }
    } elseif ($transaction?->saleLines?->isNotEmpty()) {
        foreach ($transaction->saleLines as $line) {
            $qty = $line->quantity === null ? null : rtrim(rtrim(number_format((float) $line->quantity, 4, '.', ''), '0'), '.');
            $documentLines[] = [
                'description' => $line->item_name ?: ($isPurchase ? 'Purchase' : 'Sale'),
                'remarks' => trim(implode(' | ', array_filter([$qty !== null ? 'Qty: '.$qty : null, $line->unit ? 'Unit: '.$line->unit : null]))),
                'amount' => (float) $line->line_total,
            ];
        }
    }
    if ($documentLines === []) {
        $documentLines[] = [
            'description' => $transaction?->displayHeadName($isPurchase ? 'Purchase' : 'Sales') ?? ($isPurchase ? 'Purchase' : 'Sales'),
            'remarks' => $purpose,
            'amount' => (float) $invoice->subtotal,
        ];
    }

    $summaryRows = [];
    if ($feedDocument) {
        $summaryRows[] = ['label' => 'Subtotal', 'amount' => (float) $feedDocument->subtotal];
        $summaryRows[] = ['label' => 'Commission (-)', 'amount' => (float) $feedDocument->overall_discount, 'display' => '(-) '.number_format((float) $feedDocument->overall_discount, 2)];
        $transport = (float) $feedDocument->transport_cost;
        $summaryRows[] = [
            'label' => $isPurchase ? 'Transportation (-)' : 'Transportation',
            'amount' => $transport,
            'display' => $isPurchase ? '(-) '.number_format($transport, 2) : number_format($transport, 2),
        ];
        $summaryRows[] = ['label' => 'Other Cost', 'amount' => (float) $feedDocument->other_cost];
    } else {
        $summaryRows[] = ['label' => 'Subtotal', 'amount' => (float) $invoice->subtotal];
        $summaryRows[] = ['label' => 'Discount', 'amount' => (float) $invoice->discount_amount];
        $summaryRows[] = ['label' => 'VAT / Tax', 'amount' => (float) $invoice->tax_amount];
    }
    $summaryRows[] = ['label' => 'TOTAL '.($isPurchase ? 'PURCHASE' : 'SALE'), 'amount' => (float) $invoice->total_amount, 'total' => true];
    $summaryRows[] = ['label' => 'Paid', 'amount' => (float) $invoice->paid_amount];
    $summaryRows[] = ['label' => 'Due', 'amount' => (float) $invoice->due_amount];

    $metaRows = [
        ['label' => 'Invoice No.', 'value' => $invoice->invoice_no],
        ['label' => 'Invoice Date', 'value' => $invoiceDate],
        ['label' => 'Payment Method', 'value' => $paymentMethod],
        ['label' => 'Reference No.', 'value' => $referenceNo],
    ];

    $numberToWords = function (int $number) use (&$numberToWords): string {
        $dictionary = [0=>'Zero',1=>'One',2=>'Two',3=>'Three',4=>'Four',5=>'Five',6=>'Six',7=>'Seven',8=>'Eight',9=>'Nine',10=>'Ten',11=>'Eleven',12=>'Twelve',13=>'Thirteen',14=>'Fourteen',15=>'Fifteen',16=>'Sixteen',17=>'Seventeen',18=>'Eighteen',19=>'Nineteen',20=>'Twenty',30=>'Thirty',40=>'Forty',50=>'Fifty',60=>'Sixty',70=>'Seventy',80=>'Eighty',90=>'Ninety'];
        if ($number < 0) return 'Negative '.$numberToWords(abs($number));
        if ($number < 21) return $dictionary[$number];
        if ($number < 100) { $tens=((int)floor($number/10))*10; $unit=$number%10; return $dictionary[$tens].($unit?' '.$dictionary[$unit]:''); }
        if ($number < 1000) return $dictionary[(int)floor($number/100)].' Hundred'.($number%100?' '.$numberToWords($number%100):'');
        foreach ([10000000=>'Crore',100000=>'Lakh',1000=>'Thousand'] as $base=>$label) if ($number >= $base) return $numberToWords((int)floor($number/$base)).' '.$label.($number%$base?' '.$numberToWords($number%$base):'');
        return (string) $number;
    };
    $wordAmount = (float) $invoice->total_amount;
    $decimalAmount = round(($wordAmount - floor($wordAmount)) * 100);
    $amountInWords = $numberToWords((int) floor($wordAmount)).' Taka'.($decimalAmount > 0 ? ' and '.$numberToWords((int) $decimalAmount).' Paisa' : '').' Only';
@endphp

<x-layouts::accounting :title="$invoice->invoice_no">
    <div class="hg-page-header no-print">
        <div>
            <h1>{{ $invoice->title }}</h1>
            <p>One-page document generated from posted voucher {{ $transaction?->voucher_no }}.</p>
        </div>
        <div class="hg-actions">
            <a class="hg-btn" href="{{ route('transactions.index', ['category' => $transaction?->category ?? TransactionTypes::SALE]) }}">Back to Transactions</a>
            <a class="hg-btn hg-btn-soft" href="{{ route('sales-invoices.download', $invoice) }}">Download Invoice</a>
            <button class="hg-btn hg-btn-primary" type="button" onclick="window.print()">Print / Save PDF</button>
        </div>
    </div>

    @include('accounting.partials.unified-document', [
        'documentTitle' => $documentTitle,
        'company' => $company,
        'logoUrl' => $logoUrl,
        'metaRows' => $metaRows,
        'partySectionTitle' => $isPurchase ? 'SUPPLIER / PAID TO' : 'CUSTOMER / RECEIVED FROM',
        'party' => $party,
        'partyPhoneEmail' => $phoneEmail,
        'purpose' => $purpose,
        'documentLines' => $documentLines,
        'currencyLabel' => $currencyLabel,
        'summaryRows' => $summaryRows,
        'amountInWords' => $amountInWords,
        'notes' => 'Thank you for your business.',
        'preparedByName' => $preparedByName,
        'preparedByPosition' => 'Accounts Executive',
        'preparedDate' => $invoiceDate,
        'preparedByEmail' => $preparedByEmail,
    ])
</x-layouts::accounting>
