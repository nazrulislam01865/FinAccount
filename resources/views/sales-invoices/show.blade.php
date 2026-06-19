@php
    $company = $invoice->company_snapshot ?: [];
    $customer = $invoice->customer_snapshot ?: [];
    $transaction = $invoice->transaction;
    $logoPath = $company['logo_path'] ?? null;
    $logoUrl = $logoPath ? \Illuminate\Support\Facades\Storage::disk('public')->url($logoPath) : null;
@endphp

<x-layouts::accounting :title="$invoice->invoice_no">
    <div class="hg-page-header no-print">
        <div>
            <h1>{{ $invoice->title }}</h1>
            <p>Invoice generated from sales transaction {{ $transaction?->voucher_no }}. The invoice is a customer document; ledger balances still come from the posted journal lines.</p>
        </div>
        <div class="hg-actions">
            <a class="hg-btn" href="{{ route('transactions.index', ['category' => 'Sales']) }}">Back to Transactions</a>
            <a class="hg-btn hg-btn-soft" href="{{ route('sales-invoices.download', $invoice) }}">Download Invoice</a>
            <button class="hg-btn hg-btn-primary" type="button" onclick="window.print()">Print / Save PDF</button>
        </div>
    </div>

    <section class="hg-invoice-paper">
        <div class="hg-invoice-head">
            <div class="hg-invoice-company">
                @if($logoUrl)
                    <img src="{{ $logoUrl }}" alt="{{ $company['name'] ?? 'Company' }} logo">
                @else
                    <div class="hg-invoice-logo-fallback">{{ strtoupper(substr((string) ($company['short_name'] ?? $company['name'] ?? 'HG'), 0, 2)) }}</div>
                @endif
                <div>
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
            </div>
            <div class="hg-invoice-meta">
                <h1>{{ $invoice->title }}</h1>
                <div><span>Invoice No</span><strong>{{ $invoice->invoice_no }}</strong></div>
                <div><span>Invoice Date</span><strong>{{ $invoice->invoice_date?->format('Y-m-d') }}</strong></div>
                @if($invoice->due_date)<div><span>Due Date</span><strong>{{ $invoice->due_date->format('Y-m-d') }}</strong></div>@endif
                <div><span>Status</span><strong class="hg-invoice-status {{ $invoice->status }}">{{ ucfirst($invoice->status) }}</strong></div>
            </div>
        </div>

        <div class="hg-invoice-two-col">
            <div class="hg-invoice-box">
                <h3>Bill To</h3>
                <strong>{{ $customer['name'] ?? $invoice->party?->name ?? 'Cash Customer' }}</strong>
                @if(!empty($customer['code']))<p>Code: {{ $customer['code'] }}</p>@endif
                @if(!empty($customer['type']))<p>Type: {{ $customer['type'] }}</p>@endif
            </div>
            <div class="hg-invoice-box">
                <h3>Transaction</h3>
                <p><strong>Voucher:</strong> {{ $transaction?->voucher_no }}</p>
                <p><strong>Head:</strong> {{ $transaction?->transactionHead?->name ?? '-' }}</p>
                <p><strong>Money Account:</strong> {{ $transaction?->moneyAccount?->name ?? '-' }}</p>
                @if($transaction?->reference)<p><strong>Reference:</strong> {{ $transaction->reference }}</p>@endif
            </div>
        </div>

        <div class="hg-table-wrap hg-invoice-table-wrap">
            <table class="hg-table hg-invoice-table">
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
                            @if($transaction?->description)<br><span class="hg-muted">{{ $transaction->description }}</span>@endif
                        </td>
                        <td class="right">{{ \App\Support\CompanyContext::money((float) $invoice->subtotal) }}</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div class="hg-invoice-summary">
            <div></div>
            <table>
                <tr><td>Subtotal</td><td>{{ \App\Support\CompanyContext::money((float) $invoice->subtotal) }}</td></tr>
                <tr><td>Discount</td><td>{{ \App\Support\CompanyContext::money((float) $invoice->discount_amount) }}</td></tr>
                <tr><td>Tax</td><td>{{ \App\Support\CompanyContext::money((float) $invoice->tax_amount) }}</td></tr>
                <tr class="total"><td>Total</td><td>{{ \App\Support\CompanyContext::money((float) $invoice->total_amount) }}</td></tr>
                <tr><td>Paid</td><td>{{ \App\Support\CompanyContext::money((float) $invoice->paid_amount) }}</td></tr>
                <tr class="due"><td>Due</td><td>{{ \App\Support\CompanyContext::money((float) $invoice->due_amount) }}</td></tr>
            </table>
        </div>

        <div class="hg-invoice-note">
            This invoice was generated from a posted sales transaction. Any customer receivable or paid amount is controlled by the transaction's accounting rule and journal lines.
        </div>
    </section>
</x-layouts::accounting>
