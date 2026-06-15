<?php

namespace App\Http\Controllers\Accounting;

use App\Http\Controllers\Controller;
use App\Http\Requests\Accounting\StorePartyRequest;
use App\Http\Requests\Accounting\UpdatePartyRequest;
use App\Models\Party;
use App\Services\Accounting\PartyService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class PartyController extends Controller
{
    public function __construct(private readonly PartyService $service) {}

    public function index(Request $request): View
    {
        return view('parties.index', $this->service->pageData($request->user()->company_id));
    }

    public function store(StorePartyRequest $request): RedirectResponse
    {
        $this->service->create($request->validated(), $request->user()->company_id);

        return redirect()->route('parties.index')->with('success', 'Record saved');
    }

    public function update(UpdatePartyRequest $request, Party $party): RedirectResponse
    {
        $this->ensureCompany($request, $party);
        $this->service->update($party, $request->validated());

        return redirect()->route('parties.index')->with('success', 'Record saved');
    }

    public function destroy(Request $request, Party $party): RedirectResponse
    {
        $this->ensureCompany($request, $party);
        $this->service->delete($party);

        return redirect()->route('parties.index')->with('success', 'Record deleted');
    }

    private function ensureCompany(Request $request, Party $party): void
    {
        abort_unless($party->company_id === $request->user()->company_id, 404);
    }
}
