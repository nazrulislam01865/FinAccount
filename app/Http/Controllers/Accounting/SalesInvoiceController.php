<?php

namespace App\Http\Controllers\Accounting;

use App\Http\Controllers\Controller;
use App\Models\SalesInvoice;
use App\Models\Transaction;
use App\Services\Accounting\SalesInvoiceService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class SalesInvoiceController extends Controller
{
    public function __construct(private readonly SalesInvoiceService $salesInvoiceService) {}

    public function show(Request $request, SalesInvoice $salesInvoice): View
    {
        abort_unless($salesInvoice->company_id === $request->user()->company_id, 404);

        $salesInvoice->load([
            'transaction.transactionHead',
            'transaction.moneyAccount',
            'transaction.party',
            'company',
        ]);

        return view('sales-invoices.show', [
            'invoice' => $salesInvoice,
        ]);
    }

    public function download(Request $request, SalesInvoice $salesInvoice): Response
    {
        abort_unless($salesInvoice->company_id === $request->user()->company_id, 404);

        $salesInvoice->load([
            'transaction.transactionHead',
            'transaction.moneyAccount',
            'transaction.party',
            'company',
        ]);

        $html = view('sales-invoices.download', [
            'invoice' => $salesInvoice,
        ])->render();

        $filename = preg_replace('/[^A-Za-z0-9_\-]/', '-', $salesInvoice->invoice_no).'.html';

        return response($html, 200, [
            'Content-Type' => 'text/html; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ]);
    }

    public function generate(Request $request, Transaction $transaction): RedirectResponse
    {
        abort_unless($transaction->company_id === $request->user()->company_id, 404);

        $company = $request->user()->company;
        abort_unless($company, 404);

        $invoice = $this->salesInvoiceService->syncForTransaction($transaction, $company);

        if (! $invoice) {
            return back()->with('error', 'Invoice was not generated. Enable Generate Sales Invoice on the selected sales accounting rule first.');
        }

        return back()
            ->with('success', 'Sales invoice '.$invoice->invoice_no.' generated and download started.')
            ->with('invoice_download_url', route('sales-invoices.download', $invoice))
            ->with('invoice_show_url', route('sales-invoices.show', $invoice));
    }
}
