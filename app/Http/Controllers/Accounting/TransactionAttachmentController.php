<?php

namespace App\Http\Controllers\Accounting;

use App\Http\Controllers\Controller;
use App\Models\Transaction;
use App\Models\TransactionAttachment;
use App\Services\Accounting\TransactionAttachmentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class TransactionAttachmentController extends Controller
{
    public function __construct(
        private readonly TransactionAttachmentService $attachmentService,
    ) {}

    public function show(Request $request, Transaction $transaction, TransactionAttachment $attachment): StreamedResponse
    {
        $this->ensureAccess($request, $transaction, $attachment);

        abort_unless(Storage::disk('public')->exists($attachment->stored_path), 404);

        return Storage::disk('public')->response(
            $attachment->stored_path,
            $attachment->display_name,
            [
                'Content-Type' => $attachment->mime_type ?: 'application/octet-stream',
                'X-Content-Type-Options' => 'nosniff',
            ],
            'inline',
        );
    }

    public function destroy(Request $request, Transaction $transaction, TransactionAttachment $attachment): RedirectResponse
    {
        $this->ensureAccess($request, $transaction, $attachment);
        $this->attachmentService->delete($attachment, $request->user());

        return back()->with('success', 'Transaction attachment removed successfully.');
    }

    private function ensureAccess(Request $request, Transaction $transaction, TransactionAttachment $attachment): void
    {
        abort_unless($transaction->company_id === $request->user()->company_id, 404);
        abort_unless($attachment->company_id === $request->user()->company_id, 404);
        abort_unless($attachment->transaction_id === $transaction->id, 404);
    }
}
