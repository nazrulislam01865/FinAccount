<?php

namespace App\Http\Controllers\Feed;

use App\Http\Controllers\Controller;
use App\Models\Feed\FeedItem;
use App\Models\Feed\FeedSetting;
use App\Models\Feed\FeedStockBalance;
use App\Models\Feed\FeedWarehouse;
use App\Services\Feed\FeedAccountingSetupService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use App\Http\Controllers\Concerns\PerformsSafeDelete;
use App\Services\Accounting\SafeDelete\SafeDeleteService;

class FeedSetupController extends Controller
{
    use PerformsSafeDelete;

    public function __construct(
        private readonly FeedAccountingSetupService $accountingSetupService,
        private readonly SafeDeleteService $safeDeleteService,
    ) {}

    public function index(Request $request): View
    {
        $companyId = (int) $request->user()->company_id;
        $settings = $this->accountingSetupService->ensure($companyId);

        $warehouses = FeedWarehouse::query()
            ->where('company_id', $companyId)
            ->orderByDesc('is_active')
            ->orderBy('name')
            ->get();

        $items = FeedItem::query()
            ->where('company_id', $companyId)
            ->withSum('balances as stock_quantity', 'quantity')
            ->orderByDesc('is_active')
            ->orderBy('name')
            ->get();

        return view('feed.setup.index', compact('settings', 'warehouses', 'items'));
    }

    public function storeItem(Request $request): RedirectResponse
    {
        $companyId = (int) $request->user()->company_id;
        $request->merge([
            'name' => trim((string) $request->input('name')),
        ]);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'category' => ['nullable', 'string', 'max:100'],
            'brand' => ['nullable', 'string', 'max:100'],
            'pack_size' => ['required', 'numeric', 'gt:0', 'decimal:0,4'],
            'default_purchase_price' => ['nullable', 'numeric', 'min:0'],
            'default_sale_price' => ['nullable', 'numeric', 'min:0'],
            'reorder_level' => ['nullable', 'numeric', 'min:0', 'decimal:0,4'],
            'track_batch' => ['nullable', 'boolean'],
            'track_expiry' => ['nullable', 'boolean'],
        ]);

        FeedItem::query()->create([
            'company_id' => $companyId,
            'code' => strtoupper(uniqid('FI-')),
            'name' => trim($validated['name']),
            'category' => filled($validated['category'] ?? null) ? trim($validated['category']) : null,
            'brand' => filled($validated['brand'] ?? null) ? trim($validated['brand']) : null,
            'pack_size' => number_format((float) $validated['pack_size'], 4, '.', ''),
            'base_unit' => 'KG',
            'default_purchase_price' => number_format((float) ($validated['default_purchase_price'] ?? 0), 2, '.', ''),
            'default_sale_price' => number_format((float) ($validated['default_sale_price'] ?? 0), 2, '.', ''),
            'reorder_level' => number_format((float) ($validated['reorder_level'] ?? 0), 4, '.', ''),
            'track_batch' => $request->boolean('track_batch'),
            'track_expiry' => $request->boolean('track_expiry'),
            'is_active' => true,
        ]);

        return back()->with('success', 'Feed item added successfully.');
    }

    public function toggleItem(Request $request, FeedItem $feedItem): RedirectResponse
    {
        abort_unless((int) $feedItem->company_id === (int) $request->user()->company_id, 404);

        if ($feedItem->is_active && FeedStockBalance::query()->where('feed_item_id', $feedItem->id)->where('quantity', '>', 0)->exists()) {
            throw ValidationException::withMessages([
                'feed_item' => 'This item still has stock. Sell or adjust it to zero before making it inactive.',
            ]);
        }

        $feedItem->update(['is_active' => ! $feedItem->is_active]);

        return back()->with('success', 'Feed item status updated.');
    }

    public function destroyItem(Request $request, FeedItem $feedItem): JsonResponse|RedirectResponse
    {
        abort_unless((int) $feedItem->company_id === (int) $request->user()->company_id, 404);

        return $this->performSafeDelete(
            $request,
            $this->safeDeleteService->inspectFeedItem($feedItem),
            fn () => $this->safeDeleteService->deleteFeedItem($feedItem),
            'feed.setup.index',
            'Feed item deleted permanently.',
        );
    }

    public function storeWarehouse(Request $request): RedirectResponse
    {
        $companyId = (int) $request->user()->company_id;
        $request->merge([
            'name' => trim((string) $request->input('name')),
        ]);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255', Rule::unique('feed_warehouses', 'name')->where('company_id', $companyId)],
            'location' => ['nullable', 'string', 'max:255'],
        ]);

        FeedWarehouse::query()->create([
            'company_id' => $companyId,
            'code' => strtoupper(uniqid('WH-')),
            'name' => trim($validated['name']),
            'location' => filled($validated['location'] ?? null) ? trim($validated['location']) : null,
            'is_active' => true,
        ]);

        $this->accountingSetupService->ensure($companyId);

        return back()->with('success', 'Feed warehouse added successfully.');
    }

    public function setDefaultWarehouse(Request $request, FeedWarehouse $feedWarehouse): RedirectResponse
    {
        $companyId = (int) $request->user()->company_id;
        abort_unless((int) $feedWarehouse->company_id === $companyId, 404);

        if (! $feedWarehouse->is_active) {
            throw ValidationException::withMessages([
                'warehouse' => 'Only an active warehouse can be selected as the default.',
            ]);
        }

        $settings = $this->accountingSetupService->ensure($companyId);
        $settings->update(['default_tracking_unit_id' => $feedWarehouse->id]);

        return back()->with('success', $feedWarehouse->name.' is now the default feed warehouse.');
    }

    public function toggleWarehouse(Request $request, FeedWarehouse $feedWarehouse): RedirectResponse
    {
        abort_unless((int) $feedWarehouse->company_id === (int) $request->user()->company_id, 404);

        $isDefault = FeedSetting::query()
            ->where('company_id', $request->user()->company_id)
            ->where('default_tracking_unit_id', $feedWarehouse->id)
            ->exists();

        if ($feedWarehouse->is_active && FeedStockBalance::query()->where('tracking_unit_id', $feedWarehouse->id)->where('quantity', '>', 0)->exists()) {
            throw ValidationException::withMessages([
                'warehouse' => 'This warehouse still has stock. Move or sell the stock before making it inactive.',
            ]);
        }

        $becomingInactive = $feedWarehouse->is_active;
        $feedWarehouse->update(['is_active' => ! $feedWarehouse->is_active]);

        if ($becomingInactive && $isDefault) {
            FeedSetting::query()
                ->where('company_id', $request->user()->company_id)
                ->where('default_tracking_unit_id', $feedWarehouse->id)
                ->update(['default_tracking_unit_id' => null]);
        }

        $this->accountingSetupService->ensure((int) $request->user()->company_id);

        return back()->with('success', 'Feed warehouse status updated.');
    }

    public function destroyWarehouse(Request $request, FeedWarehouse $feedWarehouse): JsonResponse|RedirectResponse
    {
        abort_unless((int) $feedWarehouse->company_id === (int) $request->user()->company_id, 404);

        return $this->performSafeDelete(
            $request,
            $this->safeDeleteService->inspectFeedWarehouse($feedWarehouse),
            fn () => $this->safeDeleteService->deleteFeedWarehouse($feedWarehouse),
            'feed.setup.index',
            'Feed warehouse deleted permanently.',
        );
    }
}
