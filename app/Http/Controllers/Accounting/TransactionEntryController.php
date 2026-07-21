<?php

namespace App\Http\Controllers\Accounting;

use App\Http\Controllers\Controller;
use App\Http\Requests\Accounting\StoreTransactionRequest;
use App\Models\AccountingOption;
use App\Models\ChartOfAccount;
use App\Models\Feed\FeedBusinessArea;
use App\Models\Feed\FeedBusinessTrackingUnit;
use App\Models\Feed\FeedItem;
use App\Models\Feed\FeedSetting;
use App\Models\Feed\FeedStockBalance;
use App\Models\Feed\FeedWarehouse;
use App\Models\MoneyAccount;
use App\Models\Party;
use App\Models\Transaction;
use App\Models\TransactionHead;
use App\Services\Accounting\AccountingOptionService;
use App\Services\Accounting\DecimalAmount;
use App\Services\Accounting\JournalBuilder;
use App\Services\Accounting\Reports\FinancialReportService;
use App\Services\Accounting\RuleMatcher;
use App\Services\Accounting\TransactionAttachmentService;
use App\Services\Accounting\TransactionEntryOptionService;
use App\Services\Accounting\TransactionPostingService;
use App\Services\Accounting\TransactionPartyResolver;
use App\Services\Accounting\TransactionSettlementService;
use App\Services\Company\CompanyAccountingPeriodService;
use App\Services\Feed\FeedPostingService;
use App\Support\SaleSellingTypes;
use App\Support\TransactionTypes;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class TransactionEntryController extends Controller
{
    public function __construct(
        private readonly JournalBuilder $journalBuilder,
        private readonly TransactionPostingService $transactionPostingService,
        private readonly TransactionSettlementService $settlementService,
        private readonly RuleMatcher $ruleMatcher,
        private readonly TransactionAttachmentService $transactionAttachmentService,
        private readonly DecimalAmount $decimalAmount,
        private readonly AccountingOptionService $optionService,
        private readonly TransactionEntryOptionService $entryOptionService,
        private readonly CompanyAccountingPeriodService $accountingPeriodService,
        private readonly TransactionPartyResolver $partyResolver,
        private readonly FeedPostingService $feedPostingService,
        private readonly FinancialReportService $reportService,
    ) {}

    public function create(Request $request): View
    {
        $company = $request->user()->company;
        abort_unless($company, 404);

        $companyId = (int) $company->id;
        $transactionCategories = $this->optionService->forGroup(AccountingOption::GROUP_TRANSACTION_CATEGORY);
        $dueSettlementContext = $this->dueSettlementContext($request, $companyId);

        $requestedCategory = $dueSettlementContext['active']
            ? (string) $dueSettlementContext['category']
            : trim($request->string('category')->toString());
        $requestedDirection = $dueSettlementContext['active']
            ? null
            : strtolower(trim($request->string('direction')->toString()));
        $requestedDirection = in_array($requestedDirection, TransactionTypes::flowCodes(), true)
            ? $requestedDirection
            : null;

        // First-time transaction entry should open the normal expense form by default.
        // Users can still change the direction or transaction type from the tabs.
        if (! $dueSettlementContext['active'] && $requestedDirection === null && $requestedCategory === '') {
            $requestedDirection = TransactionTypes::FLOW_OUTGOING;
            $requestedCategory = TransactionTypes::EXPENSE;
        }

        $requestedCategoryOption = $transactionCategories
            ->first(fn (AccountingOption $option): bool => strcasecmp($option->value, $requestedCategory) === 0);
        $hasExplicitTransactionFilter = $dueSettlementContext['active']
            || $requestedDirection !== null
            || $requestedCategoryOption !== null;
        $showTransactionTypeSelection = $hasExplicitTransactionFilter;

        $activeDirection = $requestedDirection
            ?? ($requestedCategoryOption ? $this->transactionCategoryDirection($requestedCategoryOption) : null);

        $filteredTransactionCategories = $activeDirection !== null
            ? $transactionCategories
                ->filter(fn (AccountingOption $option): bool => $this->transactionCategoryDirection($option) === $activeDirection)
                ->values()
            : collect();

        if ($dueSettlementContext['active']) {
            $categoryOption = $requestedCategoryOption;
        } elseif ($requestedCategoryOption !== null && ($activeDirection === null || $this->transactionCategoryDirection($requestedCategoryOption) === $activeDirection)) {
            $categoryOption = $requestedCategoryOption;
        } elseif ($requestedDirection !== null) {
            $categoryOption = $filteredTransactionCategories->first();
        } else {
            $categoryOption = $transactionCategories->first();
            $activeDirection = $categoryOption ? $this->transactionCategoryDirection($categoryOption) : null;
            $filteredTransactionCategories = $activeDirection !== null
                ? $transactionCategories
                    ->filter(fn (AccountingOption $option): bool => $this->transactionCategoryDirection($option) === $activeDirection)
                    ->values()
                : collect();
        }

        $category = $categoryOption?->value ?? '';
        $categoryMetadata = is_array($categoryOption?->metadata) ? $categoryOption->metadata : [];
        $isSaleCategory = SaleSellingTypes::isSaleCategory($category);
        $saleBusinessAreas = $isSaleCategory
            ? FeedBusinessArea::query()
                ->where('company_id', $companyId)
                ->where('is_active', true)
                ->orderBy('name')
                ->get()
            : collect();
        // Sales transaction entry now starts with the Business Area selector.
        // Active Business Areas come from Business Tracking setup; Others remains
        // available for plain sales without item lines.
        $saleSellingTypeOptions = $saleBusinessAreas
            ->mapWithKeys(fn (FeedBusinessArea $area): array => [$area->code => $area->name])
            ->all();
        $saleSellingTypeOptions[SaleSellingTypes::OTHERS] = 'Others';

        $saleTrackingUnits = $isSaleCategory
            ? FeedWarehouse::query()
                ->where('company_id', $companyId)
                ->where('is_active', true)
                ->orderBy('name')
                ->get()
            : collect();
        $defaultSaleWarehouseId = $isSaleCategory
            ? FeedSetting::query()->where('company_id', $companyId)->value('default_tracking_unit_id')
            : null;
        $saleCustomers = $isSaleCategory
            ? Party::query()
                ->where('company_id', $companyId)
                ->where('type', 'Customer')
                ->where('is_active', true)
                ->orderBy('name')
                ->get()
            : collect();
        $saleFeedItems = $isSaleCategory
            ? FeedItem::query()
                ->where('company_id', $companyId)
                ->where('is_active', true)
                ->orderBy('name')
                ->get()
            : collect();
        $saleBusinessItems = $isSaleCategory
            ? $this->saleBusinessItems($companyId)
            : collect();
        $saleBusinessLocations = $isSaleCategory
            ? FeedBusinessTrackingUnit::query()
                ->where('company_id', $companyId)
                ->where('is_active', true)
                ->orderBy('business_area')
                ->orderBy('name')
                ->get()
            : collect();
        $saleStockBalances = $isSaleCategory
            ? FeedStockBalance::query()
                ->where('company_id', $companyId)
                ->get()
                ->groupBy('tracking_unit_id')
                ->map(fn ($rows) => $rows->mapWithKeys(fn (FeedStockBalance $row): array => [(string) $row->feed_item_id => [
                    'quantity' => (float) $row->quantity,
                    'average_cost' => (float) $row->average_cost,
                ]]))
            : collect();

        $transactionHeadsAreUnfiltered = ! $hasExplicitTransactionFilter;
        $transactionHeadsAreDirectionFiltered = false;
        if ($transactionHeadsAreUnfiltered) {
            $transactionHeadsForEntry = $this->entryOptionService->allTransactionHeads($companyId);
        } elseif ($categoryOption !== null && $category !== '') {
            // When a direction is selected, the page automatically activates the first
            // Transaction Type tab in that direction. The head dropdown must follow
            // that active Transaction Type, not the wider direction, otherwise heads
            // from other types appear under the selected tab.
            $transactionHeadsForEntry = $this->entryOptionService->transactionHeads($companyId, $category);
        } elseif ($requestedDirection !== null) {
            $directionCategories = $filteredTransactionCategories
                ->pluck('value')
                ->map(fn ($value): string => (string) $value)
                ->all();
            $transactionHeadsForEntry = $this->entryOptionService->allTransactionHeads($companyId)
                ->filter(fn (TransactionHead $head): bool => in_array((string) $head->category, $directionCategories, true))
                ->values();
            $transactionHeadsAreDirectionFiltered = true;
        } else {
            $transactionHeadsForEntry = collect();
        }

        return view('transactions.create', [
            'category' => $category,
            'categoryOption' => $categoryOption,
            'transactionCategories' => $transactionCategories,
            'filteredTransactionCategories' => $filteredTransactionCategories,
            'transactionDirectionOptions' => TransactionTypes::flowLabels(),
            'activeTransactionDirection' => $activeDirection,
            'showTransactionTypeSelection' => $showTransactionTypeSelection,
            'transactionCategoryDirections' => $transactionCategories
                ->mapWithKeys(fn (AccountingOption $option): array => [$option->value => $this->transactionCategoryDirection($option)])
                ->all(),
            'transactionHeads' => $transactionHeadsForEntry,
            'transactionHeadsAreUnfiltered' => $transactionHeadsAreUnfiltered,
            'transactionHeadsAreDirectionFiltered' => $transactionHeadsAreDirectionFiltered,
            'moneyAccounts' => $this->entryOptionService->moneyAccounts($companyId),
            'moneyKindLabels' => $this->optionService->labels(AccountingOption::GROUP_MONEY_ACCOUNT_KIND),
            'parties' => $this->entryOptionService->parties($companyId),
            'partyTypeLabels' => $this->optionService->labels(AccountingOption::GROUP_PARTY_TYPE),
            'requestToken' => old('request_token', (string) Str::uuid()),
            'transactionDateContext' => $this->accountingPeriodService->transactionDateContext($company),
            'transactionTypeDefinition' => TransactionTypes::configuredDefinition(
                $category,
                $categoryMetadata,
                $categoryOption?->label,
            ),
            'dueSettlementContext' => $dueSettlementContext,
            'saleSellingTypeOptions' => $saleSellingTypeOptions,
            'saleBusinessAreas' => $saleBusinessAreas,
            'saleTrackingUnits' => $saleTrackingUnits,
            'defaultSaleTrackingUnitId' => $defaultSaleWarehouseId,
            'saleCustomers' => $saleCustomers,
            'saleFeedItems' => $saleFeedItems,
            'saleBusinessItemsForJs' => $saleBusinessItems,
            'saleBusinessLocations' => $saleBusinessLocations,
            'saleStockBalances' => $saleStockBalances,
        ]);
    }

    private function saleBusinessItems(int $companyId): \Illuminate\Support\Collection
    {
        return FeedBusinessTrackingUnit::query()
            ->where('company_id', $companyId)
            ->where('is_active', true)
            ->whereNotNull('items')
            ->orderBy('business_area')
            ->orderBy('name')
            ->get()
            ->flatMap(function (FeedBusinessTrackingUnit $unit): array {
                $items = is_array($unit->items) ? $unit->items : [];

                return collect($items)
                    ->map(fn (mixed $item): string => trim((string) $item))
                    ->filter()
                    ->unique()
                    ->map(fn (string $item): array => [
                        'id' => $unit->business_area.'::'.$item,
                        'name' => $item,
                        'businessArea' => (string) $unit->business_area,
                        'businessUnit' => (string) $unit->name,
                        'location' => (string) ($unit->location ?? ''),
                        'salePrice' => 0.0,
                    ])
                    ->values()
                    ->all();
            })
            ->unique(fn (array $item): string => strtolower($item['businessArea'].'|'.$item['name']))
            ->values();
    }

    public function preview(Request $request): JsonResponse
    {
        $companyId = (int) $request->user()->company_id;
        $category = $this->optionService->canonicalActiveValue(
            AccountingOption::GROUP_TRANSACTION_CATEGORY,
            (string) $request->input('category'),
        ) ?? trim((string) $request->input('category'));
        $request->merge(['category' => $category]);
        $isTransfer = $this->isTransferCategory($category);

        $validated = $request->validate([
            'category' => [
                'required',
                Rule::exists('accounting_options', 'value')->where(fn ($query) => $query
                    ->where('option_group', AccountingOption::GROUP_TRANSACTION_CATEGORY)
                    ->where('is_active', true)),
            ],
            'settlement_type' => [
                'nullable',
                Rule::exists('accounting_options', 'value')->where(fn ($query) => $query
                    ->where('option_group', AccountingOption::GROUP_SETTLEMENT_TYPE)
                    ->where('is_active', true)),
            ],
            'transaction_head_id' => [
                Rule::requiredIf(fn (): bool => ! $isTransfer),
                'nullable', 'integer',
                Rule::exists('transaction_heads', 'id')->where(fn ($query) => $query
                    ->where('company_id', $companyId)
                    ->whereRaw('LOWER(category) = ?', [strtolower($category)])
                    ->where('is_active', true)
                    ->where('code', 'not like', 'SYS-FEED-%')
                    ->whereNotNull('posting_account_id')),
            ],
            'money_account_id' => [
                'nullable', 'integer',
                Rule::exists('money_accounts', 'id')->where(fn ($query) => $query
                    ->where('company_id', $companyId)
                    ->where('is_active', true)
                    ->whereNotNull('chart_of_account_id')),
            ],
            'transfer_to_money_account_id' => [
                'nullable', 'integer', 'different:money_account_id',
                Rule::exists('money_accounts', 'id')->where(fn ($query) => $query
                    ->where('company_id', $companyId)
                    ->where('is_active', true)
                    ->whereNotNull('chart_of_account_id')),
            ],
            'party_id' => [
                'nullable', 'integer',
                Rule::exists('parties', 'id')->where(fn ($query) => $query
                    ->where('company_id', $companyId)
                    ->where('is_active', true)),
            ],
            'amount' => ['nullable', 'numeric', 'min:0'],
            'paid_amount' => ['nullable', 'numeric', 'min:0'],
        ]);

        $head = null;
        if (! $isTransfer) {
            $head = TransactionHead::query()
                ->with('postingAccount')
                ->where('company_id', $companyId)
                ->whereRaw('LOWER(category) = ?', [strtolower($category)])
                ->where('is_active', true)
                ->where('code', 'not like', 'SYS-FEED-%')
                ->whereNotNull('posting_account_id')
                ->whereHas('postingAccount', fn ($query) => $query
                    ->where('company_id', $companyId)
                    ->where('is_active', true))
                ->findOrFail($validated['transaction_head_id']);
        }

        $moneyAccount = filled($validated['money_account_id'] ?? null)
            ? MoneyAccount::query()
                ->with('chartOfAccount')
                ->where('company_id', $companyId)
                ->where('is_active', true)
                ->whereHas('chartOfAccount', fn ($query) => $query
                    ->where('company_id', $companyId)
                    ->where('is_active', true))
                ->find($validated['money_account_id'])
            : null;

        $transferToMoneyAccount = filled($validated['transfer_to_money_account_id'] ?? null)
            ? MoneyAccount::query()
                ->with('chartOfAccount')
                ->where('company_id', $companyId)
                ->where('is_active', true)
                ->whereHas('chartOfAccount', fn ($query) => $query
                    ->where('company_id', $companyId)
                    ->where('is_active', true))
                ->find($validated['transfer_to_money_account_id'])
            : null;

        $party = null;

        $scale = \App\Support\CompanyContext::decimalPlaces();
        $amount = $this->decimalAmount->normalize($validated['amount'] ?? 0, $scale);
        $settlement = [
            'settlement_type' => TransactionTypes::CASH,
            'paid_amount' => '0.00',
            'due_amount' => '0.00',
            'due_date' => null,
        ];
        $lines = [];
        $previewError = null;
        $rule = null;
        $requiresMoney = false;
        $requiresParty = false;
        $expectedPartyType = $isTransfer ? 'Any' : ($head->party_type ?: TransactionTypes::partyType($category));

        try {
            if ($isTransfer) {
                $validated['settlement_type'] = TransactionTypes::CASH;
                $validated['paid_amount'] = $amount;
            }

            $settlement = $this->settlementService->prepare($amount, $validated, $scale);
            $settlementType = $settlement['settlement_type'];

            if (! $isTransfer && ! $head->allowsSettlement($settlementType)) {
                throw ValidationException::withMessages([
                    'paid_amount' => 'The amount entered creates a payment type that is not allowed for this transaction head.',
                ]);
            }

            if ($isTransfer) {
                $requiresMoney = true;
                $requiresParty = false;
                $expectedPartyType = 'Any';

                if (! $moneyAccount) {
                    throw ValidationException::withMessages([
                        'money_account_id' => 'From account is required.',
                    ]);
                }

                if (! $transferToMoneyAccount) {
                    throw ValidationException::withMessages([
                        'transfer_to_money_account_id' => 'To account is required.',
                    ]);
                }

                $lines = $this->journalBuilder->buildTransfer($moneyAccount, $transferToMoneyAccount, $amount);
            } else {
                $rule = $this->ruleMatcher->match($companyId, $category, $settlementType, $head);
                $requiresMoney = $this->settlementService->requiresMoney($rule);
                $requiresParty = $this->settlementService->requiresParty($rule);
                $expectedPartyType = $this->partyResolver->expectedPartyType($head, $rule);
                $party = $requiresParty
                    ? $this->partyResolver->resolveRequired(
                        $companyId,
                        $head,
                        $rule,
                        $validated['party_id'] ?? null,
                    )
                    : null;

                if ($requiresMoney && ! $moneyAccount) {
                    throw ValidationException::withMessages([
                        'money_account_id' => TransactionTypes::moneyLabel($category).' is required.',
                    ]);
                }

                $lines = $this->journalBuilder->buildFromRule(
                    $head,
                    $moneyAccount,
                    $party,
                    $amount,
                    $settlement['paid_amount'],
                    $settlement['due_amount'],
                    $rule,
                );
            }
        } catch (ValidationException $exception) {
            $previewError = collect($exception->errors())->flatten()->first();
        }

        $settlementType = $settlement['settlement_type'];
        $html = view('transactions.partials.preview', [
            'head' => $head,
            'rule' => $rule,
            'lines' => $lines,
            'amount' => $amount,
            'settlement' => $settlement,
            'previewError' => $previewError,
            'sourceLabels' => $this->optionService->labels(AccountingOption::GROUP_ACCOUNTING_SOURCE),
            'partyTypeLabels' => $this->optionService->labels(AccountingOption::GROUP_RULE_PARTY_TYPE),
            'settlementLabels' => $this->optionService->labels(AccountingOption::GROUP_SETTLEMENT_TYPE),
            'transactionTypeLabel' => $this->optionService->labels(AccountingOption::GROUP_TRANSACTION_CATEGORY)[$category] ?? $category,
            'isTransfer' => $isTransfer,
        ])->render();

        return response()->json([
            'html' => $html,
            'settlementType' => $settlementType,
            'moneyRequired' => $requiresMoney,
            'partyRequired' => $requiresParty,
            'splitRequired' => $settlementType === TransactionTypes::PARTIAL,
            'dueRequired' => in_array($settlementType, [TransactionTypes::CREDIT, TransactionTypes::PARTIAL], true),
            'partyType' => $expectedPartyType,
            'autoSelectedPartyId' => $requiresParty && $party ? $party->id : null,
            'autoSelectedPartyLabel' => $requiresParty && $party ? $party->code.' — '.$party->name : null,
            'allowedSettlements' => $isTransfer ? [TransactionTypes::CASH] : $head->allowedSettlementCodes(),
            'moneyLabel' => $isTransfer ? 'From account' : TransactionTypes::moneyLabel($category),
            'transferRequired' => $isTransfer,
            'transferToLabel' => 'To account',
            'paidAmountLocked' => $isTransfer,
            'partyLabel' => TransactionTypes::partyLabel($category),
        ]);
    }

    public function store(StoreTransactionRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        if ((bool) ($validated['due_settlement'] ?? false)) {
            $this->validateDueSettlementPosting($validated, (int) $request->user()->company_id);
            $validated['settlement_type'] = TransactionTypes::CASH;
            $validated['paid_amount'] = $validated['amount'];
        }

        if ($this->shouldPostAsBusinessAreaSale($validated)) {
            $validated = $this->prepareBusinessAreaSalePayload($validated, (int) $request->user()->company_id);
        }

        if ($this->shouldPostAsFeedSale($validated)) {
            $document = $this->feedPostingService->postSale($this->feedSalePayload($validated), $request->user());
            $transaction = $document->transaction;
            $this->transactionAttachmentService->storeUploaded(
                $transaction,
                $request->file('transaction_attachments'),
                $request->user(),
            );

            return $this->redirectAfterStore(
                $request,
                $transaction,
                'Feed sale '.$transaction->voucher_no.' posted successfully. Stock, sales income, customer receivable/payment, and cost of goods sold were updated together.'
            );
        }

        $transaction = $this->transactionPostingService->post($validated, $request->user());
        $this->storeBusinessSaleLines($transaction, $validated['business_sale_lines'] ?? []);
        $this->transactionAttachmentService->storeUploaded(
            $transaction,
            $request->file('transaction_attachments'),
            $request->user(),
        );

        $transaction->loadMissing('salesInvoice');

        $message = 'Transaction '.$transaction->voucher_no.' posted successfully.';
        if ($transaction->salesInvoice) {
            $message .= ' Sales invoice '.$transaction->salesInvoice->invoice_no.' generated and download started.';
        }

        return $this->redirectAfterStore($request, $transaction, $message);
    }

    private function redirectAfterStore(Request $request, Transaction $transaction, string $message): RedirectResponse
    {
        if ($request->user()->canAccounting('transactions.view')) {
            $redirect = redirect()->route('transactions.index')->with('success', $message);

            if ($transaction->salesInvoice) {
                $redirect
                    ->with('invoice_download_url', route('sales-invoices.download', $transaction->salesInvoice))
                    ->with('invoice_show_url', route('sales-invoices.show', $transaction->salesInvoice));
            }

            return $redirect;
        }

        return redirect()
            ->route('transactions.create', ['category' => $transaction->category])
            ->with('success', $message)
            ->with('warning', 'The transaction was saved, but your role is not allowed to view the register.');
    }

    /** @param array<string, mixed> $data */
    private function shouldPostAsFeedSale(array $data): bool
    {
        $sellingType = SaleSellingTypes::normalize($data['selling_type'] ?? null);
        $lines = collect($data['lines'] ?? []);

        return SaleSellingTypes::isSaleCategory($data['category'] ?? null)
            && $sellingType === SaleSellingTypes::FEED
            && filled($data['tracking_unit_id'] ?? null)
            && $lines->isNotEmpty()
            && $lines->every(fn (mixed $line): bool => is_array($line) && is_numeric($line['item_id'] ?? null));
    }

    /** @param array<string, mixed> $data */
    private function shouldPostAsBusinessAreaSale(array $data): bool
    {
        $sellingType = SaleSellingTypes::normalize($data['selling_type'] ?? null);

        return SaleSellingTypes::isSaleCategory($data['category'] ?? null)
            && filled($sellingType)
            && ! SaleSellingTypes::isOthers($sellingType)
            && ! $this->shouldPostAsFeedSale($data);
    }

    /** @param array<string, mixed> $data @return array<string, mixed> */
    private function prepareBusinessAreaSalePayload(array $data, int $companyId): array
    {
        $businessArea = SaleSellingTypes::normalize($data['selling_type'] ?? null);
        $validItems = $this->validBusinessSaleItems($companyId, (string) $businessArea);

        if ($validItems === []) {
            throw ValidationException::withMessages([
                'lines' => 'No active items are configured for the selected business area. Add items in Business Tracking Setup first.',
            ]);
        }

        $preparedLines = [];
        $subtotal = 0.0;

        foreach ((array) ($data['lines'] ?? []) as $index => $line) {
            if (! is_array($line)) {
                continue;
            }

            $itemName = trim((string) ($line['item_name'] ?? $line['item_id'] ?? ''));
            $itemKey = mb_strtolower($itemName);

            if ($itemName === '' || ! isset($validItems[$itemKey])) {
                throw ValidationException::withMessages([
                    'lines.'.$index.'.item_name' => 'The selected item is not configured under the selected business area.',
                ]);
            }

            $quantity = round((float) ($line['quantity'] ?? 0), 4);
            $rate = round((float) ($line['rate'] ?? 0), 2);
            $gross = round($quantity * $rate, 2);

            if ($quantity <= 0) {
                throw ValidationException::withMessages(['lines.'.$index.'.quantity' => 'Quantity must be greater than zero.']);
            }

            $lineTotal = $gross;
            $subtotal = round($subtotal + $lineTotal, 2);

            $preparedLines[] = [
                'business_area' => (string) $businessArea,
                'item_name' => $validItems[$itemKey],
                'unit' => trim((string) ($line['unit'] ?? 'Unit')) ?: 'Unit',
                'quantity' => $quantity,
                'rate' => $rate,
                'discount' => 0.0,
                'line_total' => $lineTotal,
            ];
        }

        if ($preparedLines === []) {
            throw ValidationException::withMessages(['lines' => 'Add at least one item before posting the sale.']);
        }

        $otherCharges = round((float) ($data['other_charges'] ?? 0), 2);
        $total = round($subtotal + $otherCharges, 2);

        if ($total <= 0) {
            throw ValidationException::withMessages(['amount' => 'The sale total must be greater than zero.']);
        }

        $itemSummary = collect($preparedLines)
            ->map(fn (array $line): string => $line['item_name'].' × '.$line['quantity'].' @ '.number_format($line['rate'], 2, '.', '').' = '.number_format($line['line_total'], 2, '.', ''))
            ->implode('; ');
        $areaName = FeedBusinessArea::query()
            ->where('company_id', $companyId)
            ->where('code', $businessArea)
            ->value('name') ?: ucfirst((string) $businessArea);
        $description = trim((string) ($data['description'] ?? ''));
        $autoDescription = $areaName.' sale items: '.$itemSummary;
        if ($otherCharges > 0) {
            $autoDescription .= '; Other charges: '.number_format($otherCharges, 2, '.', '');
        }

        $locationLabel = FeedBusinessTrackingUnit::query()
            ->where('company_id', $companyId)
            ->where('business_area', $businessArea)
            ->where('id', (int) ($data['tracking_unit_id'] ?? 0))
            ->value('location');
        if (filled($locationLabel)) {
            $autoDescription .= '; Location: '.$locationLabel;
        }

        $data['amount'] = number_format($total, 2, '.', '');
        $data['business_sale_lines'] = $preparedLines;
        $data['description'] = $description !== '' ? $description."
".$autoDescription : $autoDescription;

        return $data;
    }

    /** @return array<string, string> */
    private function validBusinessSaleItems(int $companyId, string $businessArea): array
    {
        $activeArea = FeedBusinessArea::query()
            ->where('company_id', $companyId)
            ->where('code', $businessArea)
            ->where('is_active', true)
            ->exists();

        if (! $activeArea) {
            return [];
        }

        return FeedBusinessTrackingUnit::query()
            ->where('company_id', $companyId)
            ->where('business_area', $businessArea)
            ->where('is_active', true)
            ->whereNotNull('items')
            ->get()
            ->flatMap(fn (FeedBusinessTrackingUnit $unit) => is_array($unit->items) ? $unit->items : [])
            ->map(fn (mixed $item): string => trim((string) $item))
            ->filter()
            ->unique(fn (string $item): string => mb_strtolower($item))
            ->mapWithKeys(fn (string $item): array => [mb_strtolower($item) => $item])
            ->all();
    }

    /** @param array<int, array<string, mixed>> $lines */
    private function storeBusinessSaleLines(Transaction $transaction, array $lines): void
    {
        if ($lines === [] || ! Schema::hasTable('transaction_sale_lines')) {
            return;
        }

        DB::table('transaction_sale_lines')
            ->where('transaction_id', $transaction->id)
            ->delete();

        foreach (array_values($lines) as $index => $line) {
            DB::table('transaction_sale_lines')->insert([
                'company_id' => $transaction->company_id,
                'transaction_id' => $transaction->id,
                'business_area' => $line['business_area'],
                'item_name' => $line['item_name'],
                'unit' => $line['unit'],
                'quantity' => $line['quantity'],
                'rate' => $line['rate'],
                'discount' => $line['discount'],
                'line_total' => $line['line_total'],
                'sequence' => $index + 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    /** @param array<string, mixed> $data */
    private function feedSalePayload(array $data): array
    {
        return [
            'transaction_date' => $data['transaction_date'],
            'party_id' => $data['party_id'],
            'tracking_unit_id' => $data['tracking_unit_id'],
            'money_account_id' => $data['money_account_id'] ?? null,
            'reference' => $data['reference'] ?? null,
            'description' => $data['description'] ?? 'Feed sale from transaction entry',
            'delivery_charge' => $data['other_charges'] ?? 0,
            'overall_discount' => 0,
            'paid_amount' => $data['paid_amount'] ?? 0,
            'request_token' => $data['request_token'],
            'selling_type' => $data['selling_type'] ?? null,
            'lines' => $data['lines'] ?? [],
        ];
    }


    private function isTransferCategory(string $category): bool
    {
        $option = AccountingOption::query()
            ->forGroup(AccountingOption::GROUP_TRANSACTION_CATEGORY)
            ->where('value', $category)
            ->first();

        $metadata = is_array($option?->metadata) ? $option->metadata : [];

        return TransactionTypes::flow($category, $metadata) === TransactionTypes::FLOW_TRANSFER;
    }


    private function transactionCategoryDirection(AccountingOption $option): string
    {
        $metadata = is_array($option->metadata) ? $option->metadata : [];

        return TransactionTypes::flow((string) $option->value, $metadata);
    }

    /** @return array<string, mixed> */
    private function dueSettlementContext(Request $request, int $companyId): array
    {
        $empty = [
            'active' => false,
            'category' => null,
            'due_type' => null,
            'party_id' => null,
            'party_label' => null,
            'account_id' => null,
            'account_label' => null,
            'transaction_head_id' => null,
            'amount' => null,
            'as_of_date' => null,
            'message' => null,
        ];

        if (! $request->boolean('due_settlement')) {
            return $empty;
        }

        $dueType = $this->normalizeDueType($request->query('due_type'));
        $partyId = (int) $request->query('party_id');
        $accountId = (int) $request->query('account_id');
        $asOfDate = filled($request->query('as_of_date'))
            ? (string) $request->query('as_of_date')
            : now()->toDateString();

        if (! $dueType || $partyId <= 0 || $accountId <= 0) {
            return array_replace($empty, [
                'active' => true,
                'message' => 'The due settlement link is incomplete. Select the party and due again from Due Management.',
            ]);
        }

        $category = $dueType === 'Receivable'
            ? TransactionTypes::CUSTOMER_COLLECTION
            : TransactionTypes::SUPPLIER_PAYMENT;

        $party = Party::query()
            ->with(['receivableAccount', 'payableAccount'])
            ->where('company_id', $companyId)
            ->where('is_active', true)
            ->find($partyId);

        $account = ChartOfAccount::query()
            ->where('company_id', $companyId)
            ->where('level', 3)
            ->where('is_active', true)
            ->find($accountId);

        if (! $party || ! $account) {
            return array_replace($empty, [
                'active' => true,
                'category' => $category,
                'message' => 'The selected party or due ledger is no longer available.',
            ]);
        }

        $mappedAccountId = $dueType === 'Receivable'
            ? $party->receivable_account_id
            : $party->payable_account_id;

        if ((int) $mappedAccountId !== (int) $account->id) {
            return array_replace($empty, [
                'active' => true,
                'category' => $category,
                'message' => 'The selected party is not mapped to this due ledger anymore.',
            ]);
        }

        $currentDue = $this->currentDueBalance($companyId, (int) $party->id, (int) $account->id, $dueType, $asOfDate);
        $head = TransactionHead::query()
            ->where('company_id', $companyId)
            ->where('category', $category)
            ->where('is_active', true)
            ->whereNotNull('posting_account_id')
            ->orderByRaw('CASE WHEN posting_account_id = ? THEN 0 ELSE 1 END', [$account->id])
            ->orderBy('id')
            ->first();

        return [
            'active' => true,
            'category' => $category,
            'due_type' => $dueType,
            'party_id' => (int) $party->id,
            'party_label' => $party->code.' — '.$party->name,
            'account_id' => (int) $account->id,
            'account_label' => $account->code.' — '.$account->name,
            'transaction_head_id' => $head?->id,
            'amount' => number_format(max($currentDue, 0), \App\Support\CompanyContext::decimalPlaces(), '.', ''),
            'as_of_date' => $asOfDate,
            'message' => $currentDue > 0
                ? null
                : 'This party has no outstanding due for the selected account.',
        ];
    }

    /** @param array<string, mixed> $data */
    private function validateDueSettlementPosting(array $data, int $companyId): void
    {
        $dueType = $this->normalizeDueType($data['due_type'] ?? null);

        if (! $dueType) {
            throw ValidationException::withMessages(['amount' => 'Invalid due settlement type.']);
        }

        $expectedCategory = $dueType === 'Receivable'
            ? TransactionTypes::CUSTOMER_COLLECTION
            : TransactionTypes::SUPPLIER_PAYMENT;

        if (($data['category'] ?? null) !== $expectedCategory) {
            throw ValidationException::withMessages(['category' => 'The selected transaction type does not match this due settlement.']);
        }

        if ((int) ($data['party_id'] ?? 0) !== (int) ($data['due_party_id'] ?? 0)) {
            throw ValidationException::withMessages(['party_id' => 'The selected party does not match this due settlement.']);
        }

        $party = Party::query()
            ->with(['receivableAccount', 'payableAccount'])
            ->where('company_id', $companyId)
            ->where('is_active', true)
            ->findOrFail((int) $data['due_party_id']);

        $account = ChartOfAccount::query()
            ->where('company_id', $companyId)
            ->where('level', 3)
            ->where('is_active', true)
            ->findOrFail((int) $data['due_account_id']);

        $mappedAccountId = $dueType === 'Receivable'
            ? $party->receivable_account_id
            : $party->payable_account_id;

        if ((int) $mappedAccountId !== (int) $account->id) {
            throw ValidationException::withMessages(['party_id' => 'The selected party is not mapped to this due ledger anymore.']);
        }

        $head = TransactionHead::query()
            ->where('company_id', $companyId)
            ->where('category', $expectedCategory)
            ->where('is_active', true)
            ->findOrFail((int) $data['transaction_head_id']);

        if ((int) $head->posting_account_id !== (int) $account->id) {
            throw ValidationException::withMessages(['transaction_head_id' => 'The selected transaction head is not linked to this due ledger.']);
        }

        $currentDue = $this->currentDueBalance(
            $companyId,
            (int) $party->id,
            (int) $account->id,
            $dueType,
            (string) ($data['due_as_of_date'] ?? now()->toDateString()),
        );

        $amount = round((float) ($data['amount'] ?? 0), 2);

        if ($currentDue <= 0) {
            throw ValidationException::withMessages(['amount' => 'This party has no outstanding due for the selected account.']);
        }

        if ($amount > $currentDue) {
            throw ValidationException::withMessages(['amount' => 'The settlement amount cannot be greater than the current due balance.']);
        }
    }

    private function currentDueBalance(int $companyId, int $partyId, int $accountId, string $dueType, string $asOfDate): float
    {
        $report = $this->reportService->dueReport($companyId, [
            'as_of_date' => $asOfDate,
            'due_type' => strtolower($dueType),
            'include_zero_balances' => true,
        ]);

        $row = collect($report['rows'])->first(fn (array $item): bool =>
            (int) $item['party_id'] === $partyId
            && (int) $item['account_id'] === $accountId
            && $item['due_type'] === $dueType
        );

        return round((float) ($row['closing_balance'] ?? 0), 2);
    }

    private function normalizeDueType(mixed $value): ?string
    {
        return match (strtolower(trim((string) $value))) {
            'receivable' => 'Receivable',
            'payable' => 'Payable',
            default => null,
        };
    }

}
