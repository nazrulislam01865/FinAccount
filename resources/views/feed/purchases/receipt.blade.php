@php
    use App\Support\CompanyContext;
    use Illuminate\Support\Facades\Storage;

    $transaction = $document->transaction;
    $payments = $transaction?->payments ?? collect();
    $receiptCompany = $company ?? null;
    $companyData = [
        'name' => $receiptCompany?->name ?: 'Bashir Agro',
        'short_name' => $receiptCompany?->short_name ?: 'BA',
        'address' => $receiptCompany?->address,
        'phone' => $receiptCompany?->contact_phone,
        'email' => $receiptCompany?->contact_email,
        'website' => $receiptCompany?->website ?: 'www.Bashiragro.com',
        'logo_path' => $receiptCompany?->logo_path,
    ];
    if (str_contains(strtolower((string) $companyData['name']), 'hisebghor demo')) {
        $companyData['name'] = 'Bashir Agro';
        $companyData['short_name'] = 'BA';
    }

    $logoUrl = !empty($companyData['logo_path']) ? Storage::disk('public')->url($companyData['logo_path']) : null;
    $currencyLabel = strtoupper(CompanyContext::currencyCode() ?: 'TK');
    $currencyLabel = $currencyLabel === 'BDT' ? 'TK' : $currencyLabel;
    $documentDate = $transaction?->transaction_date?->format('M d, Y') ?? $document->created_at?->format('M d, Y') ?? '-';
    $paymentMethod = $payments->pluck('moneyAccount.name')->filter()->unique()->implode(', ');
    if ($paymentMethod === '') $paymentMethod = $transaction?->moneyAccount?->name ?: 'Supplier Payable';
    $referenceNo = $document->reference ?: ($transaction?->reference ?: ($transaction?->voucher_no ?: '-'));
    $party = [
        'name' => $document->party?->name ?: 'Supplier',
        'address' => $document->party?->address,
        'phone' => $document->party?->phone,
        'email' => $document->party?->email,
    ];
    $partyPhoneEmail = trim(($party['phone'] ?: '').(($party['phone'] && $party['email']) ? ' / ' : '').($party['email'] ?: ''));
    $purpose = $transaction?->description ?: 'Feed purchase';

    $documentLines = [];
    foreach ($document->lines as $line) {
        $qty = rtrim(rtrim(number_format((float) $line->quantity, 4, '.', ''), '0'), '.');
        $documentLines[] = [
            'description' => $line->item?->name ?: 'Feed item',
            'remarks' => 'Qty: '.$qty.' '.$line->unit.' | Rate: '.number_format((float) $line->rate, 2),
            'amount' => (float) $line->line_total,
        ];
    }

    $summaryRows = [
        ['label' => 'Subtotal', 'amount' => (float) $document->subtotal],
        ['label' => 'Commission (-)', 'amount' => (float) $document->overall_discount, 'display' => '(-) '.number_format((float) $document->overall_discount, 2)],
        ['label' => 'Transportation (-)', 'amount' => (float) $document->transport_cost, 'display' => '(-) '.number_format((float) $document->transport_cost, 2)],
        ['label' => 'Other Cost', 'amount' => (float) $document->other_cost],
        ['label' => 'TOTAL PURCHASE', 'amount' => (float) $document->total_amount, 'total' => true],
        ['label' => 'Paid', 'amount' => (float) ($transaction?->paid_amount ?? 0)],
        ['label' => 'Due', 'amount' => (float) ($transaction?->due_amount ?? 0)],
    ];
    $metaRows = [
        ['label' => 'Invoice No.', 'value' => 'PINV-'.($transaction?->voucher_no ?: $document->id)],
        ['label' => 'Invoice Date', 'value' => $documentDate],
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
    $wordAmount = (float) $document->total_amount;
    $decimalAmount = round(($wordAmount - floor($wordAmount)) * 100);
    $amountInWords = $numberToWords((int) floor($wordAmount)).' Taka'.($decimalAmount > 0 ? ' and '.$numberToWords((int) $decimalAmount).' Paisa' : '').' Only';
    $preparedByName = $document->creator?->name ?: auth()->user()?->name ?: 'System User';
    $preparedByEmail = $document->creator?->email ?: auth()->user()?->email ?: ($companyData['email'] ?? '');
@endphp

<x-layouts::accounting title="Feed Purchase Invoice">
    <div class="hg-page-header no-print">
        <div>
            <h1>Feed Purchase Invoice</h1>
            <p>One-page purchase invoice generated from voucher {{ $transaction?->voucher_no }}.</p>
        </div>
        <div class="hg-actions">
            <a class="hg-btn" href="{{ route('feed.inventory.index') }}">Back to Feed Inventory</a>
            <button class="hg-btn hg-btn-primary" type="button" onclick="window.print()">Print / Save PDF</button>
        </div>
    </div>

    @include('accounting.partials.unified-document', [
        'documentTitle' => 'FEED PURCHASE INVOICE',
        'company' => $companyData,
        'logoUrl' => $logoUrl,
        'metaRows' => $metaRows,
        'partySectionTitle' => 'SUPPLIER / PAID TO',
        'party' => $party,
        'partyPhoneEmail' => $partyPhoneEmail,
        'purpose' => $purpose,
        'documentLines' => $documentLines,
        'currencyLabel' => $currencyLabel,
        'summaryRows' => $summaryRows,
        'amountInWords' => $amountInWords,
        'notes' => 'Transportation cost and commission are deductions from the feed purchase total.',
        'preparedByName' => $preparedByName,
        'preparedByPosition' => 'Accounts Executive',
        'preparedDate' => $documentDate,
        'preparedByEmail' => $preparedByEmail,
    ])
</x-layouts::accounting>
