<?php

namespace App\Http\Controllers\Feed;

use App\Http\Controllers\Controller;
use App\Models\Feed\FeedBusinessArea;
use App\Models\Feed\FeedBusinessTrackingDefaultAssignment;
use App\Models\Feed\FeedBusinessTrackingSetting;
use App\Models\Feed\FeedBusinessTrackingUnit;
use App\Models\Feed\FeedItem;
use App\Models\Feed\FeedWarehouse;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use App\Http\Controllers\Concerns\PerformsSafeDelete;
use App\Services\Accounting\SafeDelete\SafeDeleteService;
use Illuminate\Http\JsonResponse;

class FeedBusinessTrackingController extends Controller
{
    use PerformsSafeDelete;

    public function __construct(
        private readonly SafeDeleteService $safeDeleteService,
    ) {}
    public function index(Request $request): View
    {
        $companyId = (int) $request->user()->company_id;
        $this->ensureDefaults($companyId);

        $settings = FeedBusinessTrackingSetting::query()->firstOrCreate(
            ['company_id' => $companyId],
            [
                'require_farm_tracking' => true,
                'allow_mixed_businesses' => true,
                'allow_shared_allocation' => true,
                'track_production_cycle' => true,
            ]
        );

        $businessAreaRecords = $this->businessAreaRecords($companyId, false);
        $activeBusinessAreas = $businessAreaRecords->where('is_active', true)->values();
        $businessAreas = $this->businessAreaMap($activeBusinessAreas);

        $areaOrder = $businessAreaRecords->pluck('code')->values()->all();
        $orderSql = $this->orderSqlForBusinessAreas($areaOrder);

        $units = FeedBusinessTrackingUnit::query()
            ->where('company_id', $companyId)
            ->with(['children' => fn ($query) => $query->orderByDesc('is_active')->orderBy('name')])
            ->when($orderSql !== '', fn ($query) => $query->orderByRaw($orderSql))
            ->orderByDesc('is_active')
            ->orderBy('name')
            ->get();

        $businessGroups = $units->whereNull('parent_id')->groupBy('business_area');
        $parentOptions = $units->whereNull('parent_id')->where('is_active', true)->groupBy('business_area');
        $allUnitOptions = $units->where('is_active', true)->groupBy('business_area');

        $items = FeedItem::query()
            ->where('company_id', $companyId)
            ->orderByDesc('is_active')
            ->orderBy('name')
            ->get();

        $warehouses = FeedWarehouse::query()
            ->where('company_id', $companyId)
            ->orderByDesc('is_active')
            ->orderBy('name')
            ->get();

        $assignments = FeedBusinessTrackingDefaultAssignment::query()
            ->where('company_id', $companyId)
            ->with('trackingUnit')
            ->orderByDesc('is_active')
            ->latest('id')
            ->get();

        $assignmentSourceLabels = $this->assignmentSourceLabels($assignments, $items, $warehouses);

        return view('feed.business-tracking.index', [
            'businessAreaRecords' => $businessAreaRecords,
            'businessAreas' => $businessAreas,
            'settings' => $settings,
            'units' => $units,
            'businessGroups' => $businessGroups,
            'parentOptions' => $parentOptions,
            'allUnitOptions' => $allUnitOptions,
            'items' => $items,
            'warehouses' => $warehouses,
            'assignments' => $assignments,
            'assignmentSourceLabels' => $assignmentSourceLabels,
            'businessConfigJson' => $this->businessConfigJson($activeBusinessAreas, $parentOptions),
        ]);
    }



    public function storeUnit(Request $request): RedirectResponse
    {
        $companyId = (int) $request->user()->company_id;
        $validated = $this->validateUnit($request, $companyId);

        FeedBusinessTrackingUnit::query()->create($validated + ['company_id' => $companyId]);

        return redirect()->route('feed.business-tracking.index')->with('success', 'Tracking unit saved successfully.');
    }

    public function updateUnit(Request $request, FeedBusinessTrackingUnit $trackingUnit): RedirectResponse
    {
        $companyId = (int) $request->user()->company_id;
        abort_unless((int) $trackingUnit->company_id === $companyId, 404);

        $validated = $this->validateUnit($request, $companyId, $trackingUnit);

        if (($validated['parent_id'] ?? null) && (int) $validated['parent_id'] === (int) $trackingUnit->id) {
            throw ValidationException::withMessages(['parent_id' => 'A tracking unit cannot be its own parent.']);
        }

        $trackingUnit->update($validated);

        return redirect()->route('feed.business-tracking.index')->with('success', 'Tracking unit updated successfully.');
    }

    public function destroyUnit(Request $request, FeedBusinessTrackingUnit $trackingUnit): JsonResponse|RedirectResponse
    {
        abort_unless((int) $trackingUnit->company_id === (int) $request->user()->company_id, 404);

        return $this->performSafeDelete(
            $request,
            $this->safeDeleteService->inspectFeedBusinessTrackingUnit($trackingUnit),
            fn () => $this->safeDeleteService->deleteFeedBusinessTrackingUnit($trackingUnit),
            'feed.business-tracking.index',
            'Business tracking unit deleted permanently.',
        );
    }

    public function saveRules(Request $request): RedirectResponse
    {
        $companyId = (int) $request->user()->company_id;

        FeedBusinessTrackingSetting::query()->updateOrCreate(
            ['company_id' => $companyId],
            [
                'require_farm_tracking' => $request->boolean('require_farm_tracking'),
                'allow_mixed_businesses' => $request->boolean('allow_mixed_businesses'),
                'allow_shared_allocation' => $request->boolean('allow_shared_allocation'),
                'track_production_cycle' => $request->boolean('track_production_cycle'),
            ]
        );

        return redirect()->route('feed.business-tracking.index')->with('success', 'Business tracking rules saved.');
    }

    public function storeDefaultAssignment(Request $request): RedirectResponse
    {
        $companyId = (int) $request->user()->company_id;
        $businessAreaKeys = $this->activeBusinessAreaKeys($companyId);
        $request->merge([
            'source_label' => trim((string) $request->input('source_label')),
        ]);

        $validated = $request->validate([
            'source_type' => ['required', Rule::in(array_keys(FeedBusinessTrackingDefaultAssignment::SOURCE_TYPES))],
            'source_id' => ['nullable', 'integer'],
            'source_label' => ['nullable', 'string', 'max:255'],
            'business_area' => ['required', Rule::in($businessAreaKeys)],
            'business_tracking_unit_id' => [
                'nullable',
                Rule::exists('feed_business_tracking_units', 'id')->where('company_id', $companyId),
            ],
            'allow_override' => ['nullable', 'boolean'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $this->validateAssignmentSource($companyId, $validated);
        $this->validateAssignmentUnit($companyId, $validated);

        FeedBusinessTrackingDefaultAssignment::query()->create([
            'company_id' => $companyId,
            'source_type' => $validated['source_type'],
            'source_id' => $validated['source_id'] ?? null,
            'source_label' => filled($validated['source_label'] ?? null) ? $validated['source_label'] : null,
            'business_area' => $validated['business_area'],
            'business_tracking_unit_id' => $validated['business_tracking_unit_id'] ?? null,
            'allow_override' => $request->boolean('allow_override', true),
            'is_active' => $request->boolean('is_active', true),
        ]);

        return redirect()->route('feed.business-tracking.index')->with('success', 'Default assignment added.');
    }

    public function deleteDefaultAssignment(Request $request, FeedBusinessTrackingDefaultAssignment $assignment): RedirectResponse
    {
        abort_unless((int) $assignment->company_id === (int) $request->user()->company_id, 404);
        $assignment->delete();

        return redirect()->route('feed.business-tracking.index')->with('success', 'Default assignment removed.');
    }

    private function validateUnit(Request $request, int $companyId, ?FeedBusinessTrackingUnit $trackingUnit = null): array
    {
        $businessAreaKeys = $this->activeBusinessAreaKeys($companyId);
        $request->merge([
            'name' => trim((string) $request->input('name')),
            'location' => trim((string) $request->input('location')),
            'responsible_person' => trim((string) $request->input('responsible_person')),
        ]);

        $validated = $request->validate([
            'business_area' => ['required', Rule::in($businessAreaKeys)],
            'unit_type' => ['required', 'string', 'max:80'],
            'name' => ['required', 'string', 'max:255'],
            'location' => ['nullable', 'string', 'max:255'],
            'parent_id' => [
                'nullable',
                Rule::exists('feed_business_tracking_units', 'id')->where('company_id', $companyId),
            ],
            'responsible_person' => ['nullable', 'string', 'max:255'],
            'start_date' => ['nullable', 'date'],
            'is_active' => ['required', 'boolean'],
            'description' => ['nullable', 'string', 'max:5000'],
        ]);

        if (! in_array($validated['unit_type'], $this->unitTypesForArea($companyId, $validated['business_area']), true)) {
            throw ValidationException::withMessages(['unit_type' => 'The selected unit type is not valid for this business area.']);
        }

        if (! empty($validated['parent_id'])) {
            $parent = FeedBusinessTrackingUnit::query()
                ->where('company_id', $companyId)
                ->where('id', $validated['parent_id'])
                ->first();

            if (! $parent || $parent->business_area !== $validated['business_area']) {
                throw ValidationException::withMessages(['parent_id' => 'Parent unit must belong to the same business area.']);
            }
        }

        $validated['name'] = trim($validated['name']);
        $validated['location'] = filled($validated['location'] ?? null) ? trim($validated['location']) : null;
        
        if (!$trackingUnit) {
            $prefix = strtoupper($validated['business_area']) . '-';
            $validated['code'] = strtoupper(uniqid($prefix));
        } else {
            $validated['code'] = $trackingUnit->code;
        }
        $validated['responsible_person'] = filled($validated['responsible_person'] ?? null) ? trim($validated['responsible_person']) : null;
        $validated['description'] = filled($validated['description'] ?? null) ? trim($validated['description']) : null;
        $validated['is_active'] = (bool) $validated['is_active'];
        $validated['parent_id'] = $validated['parent_id'] ?? null;

        return $validated;
    }

    private function validateAssignmentSource(int $companyId, array $validated): void
    {
        $sourceType = $validated['source_type'];
        $sourceId = $validated['source_id'] ?? null;

        if ($sourceType === 'manual') {
            if (! filled($validated['source_label'] ?? null)) {
                throw ValidationException::withMessages(['source_label' => 'Manual source name is required.']);
            }
            return;
        }

        if (! $sourceId) {
            throw ValidationException::withMessages(['source_id' => 'Please select a source.']);
        }

        $exists = $sourceType === 'feed_item'
            ? FeedItem::query()->where('company_id', $companyId)->where('id', $sourceId)->exists()
            : FeedWarehouse::query()->where('company_id', $companyId)->where('id', $sourceId)->exists();

        if (! $exists) {
            throw ValidationException::withMessages(['source_id' => 'The selected source is not valid for this company.']);
        }
    }

    private function validateAssignmentUnit(int $companyId, array $validated): void
    {
        if (empty($validated['business_tracking_unit_id'])) {
            return;
        }

        $unit = FeedBusinessTrackingUnit::query()
            ->where('company_id', $companyId)
            ->where('id', $validated['business_tracking_unit_id'])
            ->first();

        if (! $unit || $unit->business_area !== $validated['business_area']) {
            throw ValidationException::withMessages(['business_tracking_unit_id' => 'Default unit must belong to the selected business area.']);
        }
    }

    private function ensureDefaults(int $companyId): void
    {
        FeedBusinessTrackingSetting::query()->firstOrCreate(
            ['company_id' => $companyId],
            [
                'require_farm_tracking' => true,
                'allow_mixed_businesses' => true,
                'allow_shared_allocation' => true,
                'track_production_cycle' => true,
            ]
        );

        foreach (FeedBusinessArea::DEFAULT_AREAS as $code => $area) {
            FeedBusinessArea::query()->firstOrCreate(
                ['company_id' => $companyId, 'code' => $code],
                [
                    'company_id' => $companyId,
                    'code' => $code,
                    'name' => $area['name'],
                    'unit_label' => $area['unit_label'],
                    'is_active' => true,
                ]
            );
        }

        $defaults = [
            ['business_area' => 'cattle', 'unit_type' => 'Shed', 'code' => 'CAT-S01', 'name' => 'Shed 01'],
            ['business_area' => 'cattle', 'unit_type' => 'Shed', 'code' => 'CAT-S02', 'name' => 'Shed 02'],
            ['business_area' => 'fish', 'unit_type' => 'Pond', 'code' => 'FIS-P01', 'name' => 'Pond A'],
            ['business_area' => 'fish', 'unit_type' => 'Pond', 'code' => 'FIS-P02', 'name' => 'Pond B'],
            ['business_area' => 'vegetables', 'unit_type' => 'Vegetable / Crop', 'code' => 'VEG-TOM', 'name' => 'Tomato'],
            ['business_area' => 'vegetables', 'unit_type' => 'Vegetable / Crop', 'code' => 'VEG-BRI', 'name' => 'Brinjal'],
            ['business_area' => 'vegetables', 'unit_type' => 'Vegetable / Crop', 'code' => 'VEG-CHI', 'name' => 'Green Chili'],
        ];

        foreach ($defaults as $default) {
            FeedBusinessTrackingUnit::query()->firstOrCreate(
                ['company_id' => $companyId, 'code' => $default['code']],
                $default + ['company_id' => $companyId, 'is_active' => true]
            );
        }
    }

    private function businessAreaRecords(int $companyId, bool $activeOnly = true): Collection
    {
        return FeedBusinessArea::query()
            ->where('company_id', $companyId)
            ->when($activeOnly, fn ($query) => $query->where('is_active', true))
            ->orderByRaw("CASE code WHEN 'cattle' THEN 1 WHEN 'fish' THEN 2 WHEN 'vegetables' THEN 3 ELSE 99 END")
            ->orderBy('name')
            ->get();
    }

    private function businessAreaMap(Collection $businessAreaRecords): array
    {
        return $businessAreaRecords
            ->mapWithKeys(fn (FeedBusinessArea $area): array => [$area->code => $area->toTrackingOption()])
            ->all();
    }

    private function activeBusinessAreaKeys(int $companyId): array
    {
        return $this->businessAreaRecords($companyId)->pluck('code')->values()->all();
    }

    private function unitTypesForArea(int $companyId, string $businessArea): array
    {
        $area = FeedBusinessArea::query()
            ->where('company_id', $companyId)
            ->where('code', $businessArea)
            ->first();

        return $area?->unitTypes() ?? FeedBusinessTrackingUnit::unitTypesFor($businessArea);
    }

    private function businessConfigJson(Collection $businessAreaRecords, Collection $parentOptions): array
    {
        $config = [];

        foreach ($businessAreaRecords as $area) {
            $option = $area->toTrackingOption();
            $config[$area->code] = [
                'name' => $option['name'],
                'icon' => $option['icon'],
                'unitLabel' => $option['unit_label'],
                'unitTypes' => $option['unit_types'],
                'parents' => ($parentOptions->get($area->code) ?? collect())->map(fn (FeedBusinessTrackingUnit $unit): array => [
                    'id' => $unit->id,
                    'name' => $unit->name,
                ])->values()->all(),
            ];
        }

        return $config;
    }

    private function assignmentSourceLabels(Collection $assignments, Collection $items, Collection $warehouses): array
    {
        $itemMap = $items->keyBy('id');
        $warehouseMap = $warehouses->keyBy('id');
        $labels = [];

        foreach ($assignments as $assignment) {
            $label = $assignment->source_label;

            if ($assignment->source_type === 'feed_item' && $assignment->source_id) {
                $item = $itemMap->get($assignment->source_id);
                $label = $item ? $item->name : $assignment->source_label;
            }

            if ($assignment->source_type === 'warehouse' && $assignment->source_id) {
                $warehouse = $warehouseMap->get($assignment->source_id);
                $label = $warehouse ? $warehouse->name : $assignment->source_label;
            }

            $labels[$assignment->id] = filled($label) ? $label : 'Removed source';
        }

        return $labels;
    }

    private function orderSqlForBusinessAreas(array $codes): string
    {
        if ($codes === []) {
            return '';
        }

        $cases = collect($codes)
            ->values()
            ->map(fn (string $code, int $index): string => "WHEN '".str_replace("'", "''", $code)."' THEN ".($index + 1))
            ->implode(' ');

        return "CASE business_area {$cases} ELSE 999 END";
    }
}
