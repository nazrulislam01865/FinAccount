<?php

namespace App\Http\Controllers\Accounting;

use App\Http\Controllers\Controller;
use App\Models\PaymentReceipt;
use App\Models\Transaction;
use App\Services\Accounting\PaymentReceiptPdfService;
use App\Services\Accounting\PaymentReceiptService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class PaymentReceiptController extends Controller
{
    public function __construct(
        private readonly PaymentReceiptPdfService $paymentReceiptPdfService,
        private readonly PaymentReceiptService $paymentReceiptService,
    ) {}

    public function show(Request $request, PaymentReceipt $paymentReceipt): View
    {
        abort_unless($paymentReceipt->company_id === $request->user()->company_id, 404);

        $paymentReceipt->load([
            'transaction.transactionHead',
            'transaction.moneyAccount',
            'transaction.payments.moneyAccount',
            'transaction.party',
            'transaction.creator',
            'company',
            'party',
        ]);

        return view('payment-receipts.show', [
            'receipt' => $paymentReceipt,
        ]);
    }


    public function generate(Request $request, Transaction $transaction): RedirectResponse
    {
        abort_unless($transaction->company_id === $request->user()->company_id, 404);

        $company = $request->user()->company;
        abort_unless($company, 404);

        $receipt = $this->paymentReceiptService->syncForTransaction($transaction, $company);

        if (! $receipt) {
            return back()->with('error', 'Receipt was not generated because this is not a posted due collection or due payment transaction.');
        }

        return back()
            ->with('success', 'Receipt '.$receipt->receipt_no.' generated and download started.')
            ->with('receipt_download_url', route('payment-receipts.download', $receipt))
            ->with('receipt_show_url', route('payment-receipts.show', $receipt));
    }

    public function download(Request $request, PaymentReceipt $paymentReceipt): Response
    {
        abort_unless($paymentReceipt->company_id === $request->user()->company_id, 404);

        $paymentReceipt->load([
            'transaction.transactionHead',
            'transaction.moneyAccount',
            'transaction.payments.moneyAccount',
            'transaction.party',
            'transaction.creator',
            'company',
            'party',
        ]);

        $pdf = $this->paymentReceiptPdfService->render($paymentReceipt, now());
        $filename = preg_replace('/[^A-Za-z0-9_\-]/', '-', $paymentReceipt->receipt_no).'.pdf';

        return response($pdf, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
            'Cache-Control' => 'private, max-age=0, must-revalidate',
        ]);
    }
}
