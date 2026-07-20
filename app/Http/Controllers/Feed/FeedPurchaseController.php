<?php

namespace App\Http\Controllers\Feed;

use App\Http\Controllers\Controller;
use App\Http\Requests\Feed\StoreFeedPurchaseRequest;
use App\Models\AccountingOption;
use App\Models\Feed\FeedDocument;
use App\Models\Feed\FeedItem;
use App\Models\Feed\FeedWarehouse;
use App\Models\Party;
use App\Models\TransactionHead;
use App\Services\Accounting\AccountingOptionService;
use App\Services\Accounting\TransactionAttachmentService;
use App\Services\Accounting\TransactionEntryOptionService;
use App\Services\Company\CompanyAccountingPeriodService;
use App\Services\Feed\FeedAccountingSetupService;
use App\Services\Feed\FeedPostingService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use App\Support\TransactionTypes;
use Illuminate\Support\Str;

class FeedPurchaseController extends Controller
{
    public function __construct(
        private readonly FeedPostingService $feedPostingService,
        private readonly FeedAccountingSetupService $accountingSetupService,
        private readonly TransactionAttachmentService $attachmentService,
        private readonly TransactionEntryOptionService $entryOptionService,
        private readonly AccountingOptionService $optionService,
        private readonly CompanyAccountingPeriodService $accountingPeriodService,
    ) {}

    public function create(Request $request): View|RedirectResponse
    {
        $company = $request->user()->company;
        abort_unless($company, 404);
        $companyId = (int) $company->id;

        $settings = $this->accountingSetupService->ensure($companyId);

        $transactionHeads = $this->transactionHeads($companyId, TransactionTypes::PURCHASE, 'Asset');

        $items = FeedItem::query()->where('company_id', $companyId)->where('is_active', true)->orderBy('name')->get();
        $warehouses = FeedWarehouse::query()->where('company_id', $companyId)->where('is_active', true)->orderBy('name')->get();
        $suppliers = Party::query()->where('company_id', $companyId)->where('type', 'Supplier')->where('is_active', true)->orderBy('name')->get();

        if ($items->isEmpty() || $warehouses->isEmpty()) {
            return redirect()->route('feed.setup.index')->with('warning', 'Add at least one active feed item and warehouse before recording a purchase.');
        }

        return view('feed.purchases.create', [
            'settings' => $settings,
            'transactionHeads' => $transactionHeads,
            'items' => $items,
            'warehouses' => $warehouses,
            'suppliers' => $suppliers,
            'moneyAccounts' => $this->entryOptionService->moneyAccounts($companyId),
            'moneyKindLabels' => $this->optionService->labels(AccountingOption::GROUP_MONEY_ACCOUNT_KIND),
            'transactionDateContext' => $this->accountingPeriodService->transactionDateContext($company),
            'requestToken' => old('request_token', (string) Str::uuid()),
        ]);
    }


    private function transactionHeads(int $companyId, string $category, string $postingAccountType): \Illuminate\Support\Collection
    {
        return TransactionHead::query()
            ->with('postingAccount')
            ->where('company_id', $companyId)
            ->whereRaw('LOWER(category) = ?', [strtolower($category)])
            ->where('is_active', true)
            ->whereNotNull('posting_account_id')
            ->whereHas('postingAccount', fn ($query) => $query
                ->where('company_id', $companyId)
                ->where('is_active', true)
                ->where('type', $postingAccountType)
                ->where('level', 3))
            ->orderByRaw("CASE WHEN code LIKE 'SYS-FEED-%' THEN 0 ELSE 1 END")
            ->orderBy('name')
            ->get();
    }

    public function store(StoreFeedPurchaseRequest $request): RedirectResponse
    {
        $document = $this->feedPostingService->postPurchase($request->validated(), $request->user());
        $this->attachmentService->storeUploaded(
            $document->transaction,
            $request->file('transaction_attachments'),
            $request->user(),
        );

        return redirect()->route('feed.inventory.index')
            ->with('success', 'Feed purchase '.$document->transaction->voucher_no.' posted. Stock and the accounting journal were updated together.')
            ->with('feed_receipt_url', route('feed.purchases.receipt', $document));
    }

    public function receipt(Request $request, FeedDocument $feedDocument): View
    {
        abort_unless((int) $feedDocument->company_id === (int) $request->user()->company_id, 403);
        abort_unless($feedDocument->document_type === FeedDocument::TYPE_PURCHASE, 404);

        $feedDocument->load([
            'transaction.moneyAccount',
            'transaction.payments.moneyAccount',
            'transaction.transactionHead',
            'warehouse',
            'party',
            'creator',
            'lines.item',
        ]);

        return view('feed.purchases.receipt', [
            'document' => $feedDocument,
        ]);
    }
}
