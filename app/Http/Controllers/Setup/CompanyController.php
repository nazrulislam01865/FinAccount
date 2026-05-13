<?php

namespace App\Http\Controllers\Setup;

use App\Http\Controllers\Controller;
use App\Http\Requests\CompanyRequest;
use App\Models\Company;
use App\Models\FinancialYear;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class CompanyController extends Controller
{
    public function edit()
    {
        return view('setup.company', [
            'company' => Company::query()->first(),
        ]);
    }

    public function store(CompanyRequest $request): JsonResponse
    {
        $data = $request->validated();

        $data['business_type_id'] = $data['business_type_id'] ?? null;

        // Voucher numbering is configured on its own PRD setup screen, not on Company Setup.

        if ($request->hasFile('logo')) {
            $data['logo_path'] = $request->file('logo')->store('company-logos', 'public');
        }

        $company = Company::query()->first();

        if ($company) {
            $data['updated_by'] = Auth::id();
            $company->update($data);
        } else {
            $data['created_by'] = Auth::id();
            $data['updated_by'] = Auth::id();
            $company = Company::query()->create($data);
        }

        // Keep the Financial Years master table aligned with Company Setup dates required by the PRD.
        FinancialYear::query()->update(['is_active' => false]);
        FinancialYear::query()->updateOrCreate(
            [
                'company_id' => $company->id,
                'start_date' => $data['financial_year_start'],
                'end_date' => $data['financial_year_end'],
            ],
            [
                'name' => date('Y', strtotime($data['financial_year_start'])) . '-' . date('Y', strtotime($data['financial_year_end'])),
                'is_active' => true,
                'status' => 'Active',
                'created_by' => Auth::id(),
                'updated_by' => Auth::id(),
            ]
        );

        return response()->json([
            'success' => true,
            'message' => 'Company setup saved successfully.',
            'data' => $company->fresh(),
            'redirect' => route('setup.chart-of-accounts'),
        ]);
    }
}
