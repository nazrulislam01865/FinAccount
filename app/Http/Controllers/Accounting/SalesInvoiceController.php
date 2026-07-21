<?php

namespace App\Http\Controllers\Accounting;

use App\Http\Controllers\Controller;
use App\Models\SalesInvoice;
use App\Models\Transaction;
use App\Services\Accounting\SalesInvoicePdfService;
use App\Services\Accounting\SalesInvoiceService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class SalesInvoiceController extends Controller
{
    public function __construct(
        private readonly SalesInvoiceService $salesInvoiceService,
        private readonly SalesInvoicePdfService $salesInvoicePdfService,
    ) {}

    public function show(Request $request, SalesInvoice $salesInvoice): View
    {
        abort_unless($salesInvoice->company_id === $request->user()->company_id, 404);

        $salesInvoice->load([
            'transaction.transactionHead',
            'transaction.moneyAccount',
            'transaction.party',
            'transaction.saleLines',
            'transaction.feedDocument.lines.item',
            'transaction.feedDocument.warehouse',
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
            'transaction.saleLines',
            'transaction.feedDocument.lines.item',
            'transaction.feedDocument.warehouse',
            'company',
            'party',
        ]);

        $pdf = $this->salesInvoicePdfService->render($salesInvoice);
        $filename = preg_replace('/[^A-Za-z0-9_\-]/', '-', $salesInvoice->invoice_no).'.pdf';

        return response($pdf, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
            'Cache-Control' => 'private, max-age=0, must-revalidate',
        ]);
    }

    public function generate(Request $request, Transaction $transaction): RedirectResponse
    {
        abort_unless($transaction->company_id === $request->user()->company_id, 404);

        $company = $request->user()->company;
        abort_unless($company, 404);

        $invoice = $this->salesInvoiceService->syncForTransaction($transaction, $company);

        if (! $invoice) {
            return back()->with('error', 'Invoice was not generated because this is not a posted sale or purchase transaction.');
        }

        return back()
            ->with('success', $invoice->title.' '.$invoice->invoice_no.' generated and download started.')
            ->with('invoice_download_url', route('sales-invoices.download', $invoice))
            ->with('invoice_show_url', route('sales-invoices.show', $invoice));
    }
}
