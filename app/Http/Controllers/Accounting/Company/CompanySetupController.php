<?php

namespace App\Http\Controllers\Accounting\Company;

use App\Http\Controllers\Controller;
use App\Http\Requests\Company\UpdateCompanySetupRequest;
use App\Services\Company\CompanySetupService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class CompanySetupController extends Controller
{
    public function __construct(private readonly CompanySetupService $service) {}

    public function edit(Request $request): View
    {
        abort_unless($request->user()->canAnyAccounting([
            'company_setup.view',
            'company_setup.manage',
        ]), 403);

        $company = $request->user()->company;
        abort_unless($company, 404);

        return view('company-setup.edit', $this->service->pageData($company) + [
            'canManage' => $request->user()->canAccounting('company_setup.manage'),
            'canManageBranding' => $request->user()->canAccounting('settings.manage'),
        ]);
    }

    public function update(UpdateCompanySetupRequest $request): RedirectResponse
    {
        $company = $request->user()->company;
        abort_unless($company, 404);

        $this->service->update($company, $request->validated(), $request->user());

        return redirect()->route('company-setup.edit')->with('success', 'Company Setup updated successfully.');
    }
}
