<?php

namespace App\Http\Controllers\Setup;

use App\Http\Controllers\Controller;
use App\Http\Requests\CompanyRequest;
use App\Models\Company;
use App\Models\FinancialYear;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class CompanyController extends Controller
{
    public function edit()
    {
        $company = Company::query()->first();

        return view('setup.company', [
            'company' => $company,
            'financialYears' => FinancialYear::query()
                ->orderByDesc('is_current')
                ->orderByDesc('is_active')
                ->orderByDesc('start_date')
                ->get(),
            'selectedFinancialYearId' => $this->selectedFinancialYearId($company),
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

        $selectedFinancialYear = FinancialYear::query()->findOrFail((int) $data['financial_year_id']);
        $companyPayload = Arr::except($data, ['financial_year_id']);
        $companyPayload['default_financial_year_id'] = $selectedFinancialYear->id;

        $company = DB::transaction(function () use ($companyPayload, $selectedFinancialYear) {
            $company = Company::query()->first();

            if ($company) {
                $companyPayload['updated_by'] = Auth::id();
                $company->update($companyPayload);
            } else {
                $companyPayload['created_by'] = Auth::id();
                $companyPayload['updated_by'] = Auth::id();
                $company = Company::query()->create($companyPayload);
            }

            // The Financial Year selected in Company Setup becomes the single default/current FY for the project.
            FinancialYear::query()
                ->where('company_id', $company->id)
                ->orWhereNull('company_id')
                ->update([
                    'is_active' => false,
                    'is_current' => false,
                    'updated_by' => Auth::id(),
                ]);

            $selectedFinancialYear->forceFill([
                'company_id' => $company->id,
                'is_active' => true,
                'is_current' => true,
                'status' => FinancialYear::STATUS_OPEN,
                'updated_by' => Auth::id(),
            ])->save();

            $company->forceFill([
                'default_financial_year_id' => $selectedFinancialYear->id,
                'financial_year_start' => $selectedFinancialYear->start_date,
                'financial_year_end' => $selectedFinancialYear->end_date,
                'updated_by' => Auth::id(),
            ])->save();

            return $company->fresh();
        });

        return response()->json([
            'success' => true,
            'message' => 'Company setup saved successfully.',
            'data' => $company,
            'redirect' => route('setup.chart-of-accounts'),
        ]);
    }

    private function selectedFinancialYearId(?Company $company): ?int
    {
        if ($company?->default_financial_year_id) {
            $selected = FinancialYear::query()->find($company->default_financial_year_id);

            if ($selected) {
                return (int) $selected->id;
            }
        }

        if ($company?->financial_year_start && $company?->financial_year_end) {
            $selected = FinancialYear::query()
                ->whereDate('start_date', $company->financial_year_start->toDateString())
                ->whereDate('end_date', $company->financial_year_end->toDateString())
                ->orderByDesc('is_current')
                ->orderByDesc('is_active')
                ->orderByDesc('id')
                ->first();

            if ($selected) {
                return (int) $selected->id;
            }
        }

        return FinancialYear::query()
            ->where(function ($query) {
                $query->where('is_current', true)
                    ->orWhere('is_active', true);
            })
            ->orderByDesc('is_current')
            ->orderByDesc('is_active')
            ->orderByDesc('start_date')
            ->value('id');
    }
}
