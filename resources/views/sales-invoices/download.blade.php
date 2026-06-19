@php
    $company = $invoice->company_snapshot ?: [];
    $customer = $invoice->customer_snapshot ?: [];
    $transaction = $invoice->transaction;
@endphp
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{{ $invoice->invoice_no }}</title>
    <style>
        * { box-sizing: border-box; }
        body { margin: 0; padding: 28px; font-family: Arial, sans-serif; color: #111827; background: #ffffff; }
        .paper { max-width: 900px; margin: 0 auto; border: 1px solid #d8dee8; border-radius: 14px; padding: 32px; }
        .top { display: flex; justify-content: space-between; gap: 24px; border-bottom: 2px solid #e5e7eb; padding-bottom: 22px; }
        .company h2, .meta h1 { margin: 0 0 8px; }
        .company p, .meta p, .box p { margin: 4px 0; color: #475569; }
        .meta { text-align: right; }
        .status { display: inline-block; padding: 5px 10px; border-radius: 999px; background: #ecfdf5; color: #047857; font-weight: 700; }
        .two { display: grid; grid-template-columns: 1fr 1fr; gap: 18px; margin: 24px 0; }
        .box { border: 1px solid #e5e7eb; border-radius: 12px; padding: 16px; }
        .box h3 { margin: 0 0 8px; font-size: 15px; color: #334155; }
        table { width: 100%; border-collapse: collapse; }
        th { text-align: left; color: #334155; background: #f8fafc; }
        th, td { border-bottom: 1px solid #e5e7eb; padding: 12px; }
        .right { text-align: right; }
        .summary { display: flex; justify-content: flex-end; margin-top: 20px; }
        .summary table { width: 360px; }
        .summary .total td { font-size: 18px; font-weight: 800; }
        .summary .due td { color: #b91c1c; font-weight: 800; }
        .note { margin-top: 28px; padding-top: 16px; border-top: 1px solid #e5e7eb; color: #64748b; font-size: 13px; }
        @media print { body { padding: 0; } .paper { border: 0; border-radius: 0; } }
    </style>
</head>
<body>
    <main class="paper">
        <section class="top">
            <div class="company">
                <h2>{{ $company['name'] ?? $invoice->company?->name }}</h2>
                @if(!empty($company['address']))<p>{{ $company['address'] }}</p>@endif
                <p>
                    @if(!empty($company['phone'])) Phone: {{ $company['phone'] }} @endif
                    @if(!empty($company['email'])) @if(!empty($company['phone'])) · @endif Email: {{ $company['email'] }} @endif
                </p>
                @if(!empty($company['tin']) || !empty($company['bin_vat_registration_no']))
                    <p>
                        @if(!empty($company['tin'])) TIN: {{ $company['tin'] }} @endif
                        @if(!empty($company['bin_vat_registration_no'])) @if(!empty($company['tin'])) · @endif BIN/VAT: {{ $company['bin_vat_registration_no'] }} @endif
                    </p>
                @endif
            </div>
            <div class="meta">
                <h1>{{ $invoice->title }}</h1>
                <p><strong>Invoice No:</strong> {{ $invoice->invoice_no }}</p>
                <p><strong>Invoice Date:</strong> {{ $invoice->invoice_date?->format('Y-m-d') }}</p>
                @if($invoice->due_date)<p><strong>Due Date:</strong> {{ $invoice->due_date->format('Y-m-d') }}</p>@endif
                <p><span class="status">{{ ucfirst($invoice->status) }}</span></p>
            </div>
        </section>

        <section class="two">
            <div class="box">
                <h3>Bill To</h3>
                <strong>{{ $customer['name'] ?? $invoice->party?->name ?? 'Cash Customer' }}</strong>
                @if(!empty($customer['code']))<p>Code: {{ $customer['code'] }}</p>@endif
                @if(!empty($customer['type']))<p>Type: {{ $customer['type'] }}</p>@endif
            </div>
            <div class="box">
                <h3>Transaction</h3>
                <p><strong>Voucher:</strong> {{ $transaction?->voucher_no }}</p>
                <p><strong>Head:</strong> {{ $transaction?->transactionHead?->name ?? '-' }}</p>
                <p><strong>Money Account:</strong> {{ $transaction?->moneyAccount?->name ?? '-' }}</p>
                @if($transaction?->reference)<p><strong>Reference:</strong> {{ $transaction->reference }}</p>@endif
            </div>
        </section>

        <table>
            <thead>
                <tr>
                    <th>Description</th>
                    <th class="right">Amount</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>
                        <strong>{{ $transaction?->transactionHead?->name ?? 'Sales' }}</strong>
                        @if($transaction?->description)<br><small>{{ $transaction->description }}</small>@endif
                    </td>
                    <td class="right">{{ \App\Support\CompanyContext::money((float) $invoice->subtotal) }}</td>
                </tr>
            </tbody>
        </table>

        <div class="summary">
            <table>
                <tr><td>Subtotal</td><td class="right">{{ \App\Support\CompanyContext::money((float) $invoice->subtotal) }}</td></tr>
                <tr><td>Discount</td><td class="right">{{ \App\Support\CompanyContext::money((float) $invoice->discount_amount) }}</td></tr>
                <tr><td>Tax</td><td class="right">{{ \App\Support\CompanyContext::money((float) $invoice->tax_amount) }}</td></tr>
                <tr class="total"><td>Total</td><td class="right">{{ \App\Support\CompanyContext::money((float) $invoice->total_amount) }}</td></tr>
                <tr><td>Paid</td><td class="right">{{ \App\Support\CompanyContext::money((float) $invoice->paid_amount) }}</td></tr>
                <tr class="due"><td>Due</td><td class="right">{{ \App\Support\CompanyContext::money((float) $invoice->due_amount) }}</td></tr>
            </table>
        </div>

        <div class="note">
            This invoice was generated from posted voucher {{ $transaction?->voucher_no }}. Ledger and balances remain controlled by journal lines.
        </div>
    </main>
</body>
</html>
