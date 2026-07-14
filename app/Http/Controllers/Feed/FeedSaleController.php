<?php

namespace App\Http\Controllers\Feed;

use App\Http\Controllers\Controller;
use App\Http\Requests\Feed\StoreFeedSaleRequest;
use App\Models\AccountingOption;
use App\Models\Feed\FeedItem;
use App\Models\Feed\FeedStockBalance;
use App\Models\Feed\FeedWarehouse;
use App\Models\Party;
use App\Services\Accounting\AccountingOptionService;
use App\Services\Accounting\TransactionAttachmentService;
use App\Services\Accounting\TransactionEntryOptionService;
use App\Services\Company\CompanyAccountingPeriodService;
use App\Services\Feed\FeedAccountingSetupService;
use App\Services\Feed\FeedPostingService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class FeedSaleController extends Controller
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

        $items = FeedItem::query()->where('company_id', $companyId)->where('is_active', true)->orderBy('name')->get();
        $warehouses = FeedWarehouse::query()->where('company_id', $companyId)->where('is_active', true)->orderBy('name')->get();
        $customers = Party::query()->where('company_id', $companyId)->where('type', 'Customer')->where('is_active', true)->orderBy('name')->get();
        $stockBalances = FeedStockBalance::query()
            ->where('company_id', $companyId)
            ->get()
            ->groupBy('tracking_unit_id')
            ->map(fn ($rows) => $rows->mapWithKeys(fn ($row) => [(string) $row->feed_item_id => [
                'quantity' => (float) $row->quantity,
                'average_cost' => (float) $row->average_cost,
            ]]));

        if ($items->isEmpty() || $warehouses->isEmpty()) {
            return redirect()->route('feed.setup.index')->with('warning', 'Add at least one active feed item and warehouse before recording a sale.');
        }

        return view('feed.sales.create', [
            'settings' => $settings,
            'items' => $items,
            'warehouses' => $warehouses,
            'customers' => $customers,
            'stockBalances' => $stockBalances,
            'moneyAccounts' => $this->entryOptionService->moneyAccounts($companyId),
            'moneyKindLabels' => $this->optionService->labels(AccountingOption::GROUP_MONEY_ACCOUNT_KIND),
            'transactionDateContext' => $this->accountingPeriodService->transactionDateContext($company),
            'requestToken' => old('request_token', (string) Str::uuid()),
        ]);
    }

    public function store(StoreFeedSaleRequest $request): RedirectResponse
    {
        $document = $this->feedPostingService->postSale($request->validated(), $request->user());
        $this->attachmentService->storeUploaded(
            $document->transaction,
            $request->file('transaction_attachments'),
            $request->user(),
        );

        return redirect()->route('feed.inventory.index')->with(
            'success',
            'Feed sale '.$document->transaction->voucher_no.' posted. Stock, sales income, receivable/payment, and cost of goods sold were updated together.',
        );
    }
}
