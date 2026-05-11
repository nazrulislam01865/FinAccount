<?php

namespace App\Http\Controllers\Setup;

use App\Http\Controllers\Controller;
use App\Http\Requests\CompanyRequest;
use App\Models\Company;
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

        $data['journal_voucher_prefix'] = $data['journal_voucher_prefix'] ?: 'JV';
        $data['payment_voucher_prefix'] = $data['payment_voucher_prefix'] ?: 'PV';
        $data['receipt_voucher_prefix'] = $data['receipt_voucher_prefix'] ?: 'RV';
        $data['enable_multi_branch'] = $data['enable_multi_branch'] ?? false;

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

        return response()->json([
            'success' => true,
            'message' => 'Company setup saved successfully.',
            'data' => $company->fresh(),
            'redirect' => route('setup.chart-of-accounts'),
        ]);
    }
}
