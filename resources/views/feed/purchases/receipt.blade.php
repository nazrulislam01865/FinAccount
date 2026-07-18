@php
    use App\Support\CompanyContext;

    $transaction = $document->transaction;
    $currencyCode = CompanyContext::currencyCode();
    $currencySymbol = $currencyCode === 'BDT' ? 'Tk' : CompanyContext::currencySymbol();
    $formatAmount = static function (mixed $amount) use ($currencySymbol): string {
        $amount = round((float) ($amount ?? 0), 2);
        $decimals = abs($amount - round($amount)) > 0.000001 ? 2 : 0;

        return number_format($amount, $decimals, '.', '').$currencySymbol;
    };
    $formatDeduction = static fn (mixed $amount): string => '(-) '.$formatAmount($amount);
    $formatQuantity = static fn (mixed $quantity): string => rtrim(rtrim(number_format((float) ($quantity ?? 0), 4, '.', ''), '0'), '.');
@endphp

<x-layouts::accounting title="Feed Purchase Receipt">
    <style>
        .feed-receipt-shell { max-width: 980px; margin: 0 auto; }
        .feed-receipt-actions { display: flex; justify-content: space-between; align-items: center; gap: 12px; margin-bottom: 16px; }
        .feed-receipt-card { background: #fff; border: 1px solid #d8e0ed; border-radius: 24px; box-shadow: 0 18px 45px rgba(15, 23, 42, .08); overflow: hidden; }
        .feed-receipt-head { display: flex; justify-content: space-between; gap: 18px; padding: 28px 32px; color: #fff; background: linear-gradient(135deg, #174b73, #2563eb); }
        .feed-receipt-head h1 { margin: 0 0 6px; font-size: 30px; line-height: 1.1; }
        .feed-receipt-head p { margin: 0; opacity: .9; }
        .feed-receipt-badge { align-self: flex-start; border: 1px solid rgba(255,255,255,.45); border-radius: 999px; padding: 8px 14px; font-weight: 800; white-space: nowrap; }
        .feed-receipt-body { padding: 28px 32px 32px; }
        .feed-receipt-meta { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 12px; margin-bottom: 24px; }
        .feed-receipt-meta-item { border: 1px solid #e4eaf3; background: #f8fafc; border-radius: 16px; padding: 13px 14px; }
        .feed-receipt-meta-item span { display: block; color: #64748b; font-size: 12px; font-weight: 800; text-transform: uppercase; letter-spacing: .05em; }
        .feed-receipt-meta-item strong { display: block; color: #102033; font-size: 15px; margin-top: 4px; }
        .feed-receipt-table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        .feed-receipt-table th { color: #475569; text-align: left; font-size: 12px; text-transform: uppercase; letter-spacing: .05em; border-bottom: 1px solid #dbe3ef; padding: 10px 8px; }
        .feed-receipt-table td { border-bottom: 1px solid #edf2f7; padding: 12px 8px; vertical-align: top; }
        .feed-receipt-table .right { text-align: right; }
        .feed-receipt-small { color: #64748b; font-size: 12px; margin-top: 3px; }
        .feed-receipt-totals { width: min(420px, 100%); margin-left: auto; margin-top: 22px; border: 1px solid #e4eaf3; border-radius: 18px; overflow: hidden; }
        .feed-receipt-total-row { display: flex; justify-content: space-between; gap: 18px; padding: 12px 16px; border-bottom: 1px solid #edf2f7; }
        .feed-receipt-total-row:last-child { border-bottom: 0; }
        .feed-receipt-total-row strong { font-size: 16px; }
        .feed-receipt-grand { background: #102033; color: #fff; font-weight: 900; }
        .feed-receipt-deduction { color: #dc2626; font-weight: 900; }
        .feed-receipt-footer { display: flex; justify-content: space-between; gap: 28px; margin-top: 46px; color: #475569; font-size: 13px; }
        .feed-receipt-sign { min-width: 180px; text-align: center; border-top: 1px solid #94a3b8; padding-top: 8px; color: #102033; font-weight: 800; }
        @media (max-width: 860px) {
            .feed-receipt-head, .feed-receipt-actions, .feed-receipt-footer { flex-direction: column; align-items: stretch; }
            .feed-receipt-meta { grid-template-columns: repeat(2, minmax(0, 1fr)); }
            .feed-receipt-body, .feed-receipt-head { padding: 22px; }
        }
        @media print {
            .hg-side, .hg-topbar, .hg-alert, .no-print, .hg-footer, footer { display: none !important; }
            .hg-app, .hg-main, .hg-content { display: block !important; margin: 0 !important; padding: 0 !important; width: 100% !important; }
            body { background: #fff !important; }
            .feed-receipt-shell { max-width: none; margin: 0; }
            .feed-receipt-card { border: 0; border-radius: 0; box-shadow: none; }
            .feed-receipt-head { color: #111827; background: #fff; border-bottom: 2px solid #111827; }
            .feed-receipt-badge { color: #111827; border-color: #111827; }
        }
    </style>

    <div class="feed-receipt-shell">
        <div class="feed-receipt-actions no-print">
            <a class="feed-btn" href="{{ route('feed.inventory.index') }}">← Back to Feed Inventory</a>
            <button class="feed-btn feed-btn-primary" type="button" onclick="window.print()">Print Receipt</button>
        </div>

        <section class="feed-receipt-card">
            <header class="feed-receipt-head">
                <div>
                    <h1>Feed Purchase Receipt</h1>
                    <p>Purchase voucher {{ $transaction?->voucher_no ?? '—' }}</p>
                </div>
                <div class="feed-receipt-badge">Posted</div>
            </header>

            <div class="feed-receipt-body">
                <div class="feed-receipt-meta">
                    <div class="feed-receipt-meta-item"><span>Date</span><strong>{{ $transaction?->transaction_date?->format('Y-m-d') ?? $document->created_at?->format('Y-m-d') }}</strong></div>
                    <div class="feed-receipt-meta-item"><span>Supplier</span><strong>{{ $document->party?->name ?? '—' }}</strong></div>
                    <div class="feed-receipt-meta-item"><span>Warehouse</span><strong>{{ $document->warehouse?->name ?? '—' }}</strong></div>
                    <div class="feed-receipt-meta-item"><span>Payment</span><strong>{{ $transaction?->moneyAccount?->name ?? 'Supplier Payable' }}</strong></div>
                </div>

                <table class="feed-receipt-table">
                    <thead>
                        <tr>
                            <th>Feed item</th>
                            <th class="right">Qty</th>
                            <th>Unit</th>
                            <th class="right">Rate</th>
                            <th class="right">Per bag price</th>
                            <th class="right">Line total</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($document->lines as $line)
                            @php
                                $packSize = (float) ($line->item?->pack_size ?? 0);
                                $rate = (float) $line->rate;
                                $perBagRate = strtoupper((string) $line->unit) === 'BAG'
                                    ? $rate
                                    : ($packSize > 0 ? $rate * $packSize : $rate);
                            @endphp
                            <tr>
                                <td>
                                    <strong>{{ $line->item?->name ?? 'Feed item' }}</strong>
                                    <div class="feed-receipt-small">{{ $line->item?->code }}{{ $line->batch_no ? ' · Batch: '.$line->batch_no : '' }}{{ $line->expiry_date ? ' · Exp: '.$line->expiry_date->format('Y-m-d') : '' }}</div>
                                </td>
                                <td class="right">{{ $formatQuantity($line->quantity) }}</td>
                                <td>{{ $line->unit }}</td>
                                <td class="right">{{ $formatAmount($line->rate) }}</td>
                                <td class="right">{{ $formatAmount($perBagRate) }}</td>
                                <td class="right"><strong>{{ $formatAmount($line->line_total) }}</strong></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>

                <div class="feed-receipt-totals">
                    <div class="feed-receipt-total-row"><span>Items subtotal</span><strong>{{ $formatAmount($document->subtotal) }}</strong></div>
                    <div class="feed-receipt-total-row"><span>Commission</span><strong class="feed-receipt-deduction">{{ $formatDeduction($document->overall_discount) }}</strong></div>
                    <div class="feed-receipt-total-row"><span>Transportation cost</span><strong class="feed-receipt-deduction">{{ $formatDeduction($document->transport_cost) }}</strong></div>
                    <div class="feed-receipt-total-row"><span>Other cost</span><strong>{{ $formatAmount($document->other_cost) }}</strong></div>
                    <div class="feed-receipt-total-row feed-receipt-grand"><span>Total purchase</span><strong>{{ $formatAmount($document->total_amount) }}</strong></div>
                    <div class="feed-receipt-total-row"><span>Paid now</span><strong>{{ $formatAmount($transaction?->paid_amount ?? 0) }}</strong></div>
                    <div class="feed-receipt-total-row"><span>Supplier payable</span><strong>{{ $formatAmount($transaction?->due_amount ?? 0) }}</strong></div>
                </div>

                <div class="feed-receipt-footer">
                    <div>
                        <strong>Note:</strong> Transportation cost and commission are deductions on feed purchase, shown as <strong class="feed-receipt-deduction">(-)</strong> amounts.
                    </div>
                    <div class="feed-receipt-sign">Authorized Signature</div>
                </div>
            </div>
        </section>
    </div>
</x-layouts::accounting>
