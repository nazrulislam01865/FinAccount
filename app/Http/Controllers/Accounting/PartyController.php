<?php

namespace App\Http\Controllers\Accounting;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Concerns\PerformsSafeDelete;
use App\Http\Controllers\Concerns\RedirectsByAccountingAccess;
use App\Http\Requests\Accounting\StorePartyRequest;
use App\Http\Requests\Accounting\UpdatePartyRequest;
use App\Models\Party;
use App\Services\Accounting\PartyService;
use App\Services\Accounting\SafeDelete\SafeDeleteService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class PartyController extends Controller
{
    use PerformsSafeDelete, RedirectsByAccountingAccess;

    public function __construct(
        private readonly PartyService $service,
        private readonly SafeDeleteService $safeDeleteService,
    ) {}

    public function index(Request $request): View
    {
        $data = $this->service->pageData($request->user()->company_id);
        $data['addOnlyMode'] = ! $request->user()->canAccounting('parties.view');
        if ($data['addOnlyMode']) {
            $data['parties'] = collect();
            $data['balances'] = [];
        }

        return view('parties.index', $data);
    }

    public function store(StorePartyRequest $request): RedirectResponse
    {
        $data = $request->validated();
        if ($request->hasFile('profile_pic')) {
            $data['profile_pic'] = $request->file('profile_pic')->store('parties', 'public');
        }

        $this->service->create($data, $request->user()->company_id);

        return $this->redirectAfterAccountingSave($request, 'parties.view', 'parties.index', 'Record saved');
    }

    public function update(UpdatePartyRequest $request, Party $party): RedirectResponse
    {
        $this->ensureCompany($request, $party);
        
        $data = $request->validated();
        if ($request->hasFile('profile_pic')) {
            if ($party->profile_pic) {
                \Illuminate\Support\Facades\Storage::disk('public')->delete($party->profile_pic);
            }
            $data['profile_pic'] = $request->file('profile_pic')->store('parties', 'public');
        }

        $this->service->update($party, $data);

        return $this->redirectAfterAccountingSave($request, 'parties.view', 'parties.index', 'Record saved');
    }

    public function destroy(Request $request, Party $party): JsonResponse|RedirectResponse
    {
        $this->ensureCompany($request, $party);
        $plan = $this->safeDeleteService->inspectParty($party);

        return $this->performSafeDelete(
            $request,
            $plan,
            fn () => $this->safeDeleteService->deleteParty($party),
            'parties.index',
            'Party deleted permanently.',
        );
    }

    private function ensureCompany(Request $request, Party $party): void
    {
        abort_unless($party->company_id === $request->user()->company_id, 404);
    }
}
