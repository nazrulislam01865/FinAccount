<?php

namespace App\Http\Controllers\Feed;

use App\Http\Controllers\Controller;
use App\Models\Feed\FeedBusinessArea;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class FeedBusinessAreaController extends Controller
{
    public function index(Request $request): View
    {
        $companyId = (int) $request->user()->company_id;
        
        $businessAreaRecords = FeedBusinessArea::query()
            ->where('company_id', $companyId)
            ->orderByRaw("CASE code WHEN 'cattle' THEN 1 WHEN 'fish' THEN 2 WHEN 'vegetables' THEN 3 ELSE 99 END")
            ->orderBy('name')
            ->get();

        return view('master-data.business-areas.index', [
            'businessAreaRecords' => $businessAreaRecords,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $companyId = (int) $request->user()->company_id;
        $request->merge([
            'area_name' => trim((string) $request->input('area_name')),
            'area_unit_label' => trim((string) $request->input('area_unit_label')),
        ]);

        $validated = $request->validate([
            'area_name' => ['required', 'string', 'max:120'],
            'area_unit_label' => ['required', 'string', 'max:80'],
            'area_is_active' => ['required', 'boolean'],
        ]);

        $code = FeedBusinessArea::normalizeCode($validated['area_name']);

        $exists = FeedBusinessArea::query()
            ->where('company_id', $companyId)
            ->where('code', $code)
            ->exists();

        if ($exists) {
            throw ValidationException::withMessages(['area_name' => 'This business area already exists.']);
        }

        FeedBusinessArea::query()->create([
            'company_id' => $companyId,
            'code' => $code,
            'name' => $validated['area_name'],
            'unit_label' => $validated['area_unit_label'],
            'is_active' => (bool) $validated['area_is_active'],
        ]);

        return redirect()->route('master.business-areas.index')->with('success', 'Business area added successfully.');
    }
}
