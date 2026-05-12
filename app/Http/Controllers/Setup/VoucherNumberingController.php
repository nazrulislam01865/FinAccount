<?php

namespace App\Http\Controllers\Setup;

use App\Http\Controllers\Controller;
use App\Http\Requests\VoucherNumberingRuleRequest;
use App\Models\FinancialYear;
use App\Models\VoucherNumberingRule;
use App\Services\Setup\VoucherNumberingRuleService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class VoucherNumberingController extends Controller
{
    public function index(): View
    {
        $financialYears = FinancialYear::query()
            ->where('status', 'Active')
            ->orderByDesc('is_active')
            ->orderByDesc('start_date')
            ->get();

        $currentFinancialYear = $financialYears->firstWhere('is_active', true)
            ?: $financialYears->first();

        $rules = VoucherNumberingRule::query()
            ->with('financialYear')
            ->when($currentFinancialYear, fn ($query) => $query
                ->where('financial_year_id', $currentFinancialYear->id))
            ->orderByRaw("
                CASE voucher_type
                    WHEN 'Payment Voucher' THEN 1
                    WHEN 'Receipt Voucher' THEN 2
                    WHEN 'Journal Voucher' THEN 3
                    WHEN 'Contra / Transfer Voucher' THEN 4
                    WHEN 'Draft Voucher' THEN 5
                    ELSE 99
                END
            ")
            ->orderBy('voucher_type')
            ->get();

        $activeCount = $rules->where('status', 'Active')->count();

        $duplicatePrefixIssue = $rules
            ->where('status', 'Active')
            ->groupBy('prefix')
            ->filter(fn ($items) => $items->count() > 1)
            ->isNotEmpty();

        return view('setup.voucher-numbering', [
            'rules' => $rules,
            'voucherTypes' => VoucherNumberingRule::VOUCHER_TYPES,
            'defaultPrefixes' => VoucherNumberingRule::DEFAULT_PREFIXES,
            'financialYears' => $financialYears,
            'currentFinancialYear' => $currentFinancialYear,
            'activeCount' => $activeCount,
            'duplicatePrefixIssue' => $duplicatePrefixIssue,
            'currentYear' => now()->format('Y'),
        ]);
    }

    public function store(
        VoucherNumberingRuleRequest $request,
        VoucherNumberingRuleService $service
    ): JsonResponse {
        $rule = $service->create(
            $request->validated(),
            $request->user()?->id
        );

        return response()->json([
            'success' => true,
            'message' => 'Voucher numbering rule saved successfully.',
            'data' => $rule,
            'redirect' => route('setup.voucher-numbering'),
        ], 201);
    }

    public function update(
        VoucherNumberingRuleRequest $request,
        VoucherNumberingRule $voucherNumberingRule,
        VoucherNumberingRuleService $service
    ): JsonResponse {
        $rule = $service->update(
            $voucherNumberingRule,
            $request->validated(),
            $request->user()?->id
        );

        return response()->json([
            'success' => true,
            'message' => 'Voucher numbering rule updated successfully.',
            'data' => $rule,
            'redirect' => route('setup.voucher-numbering'),
        ]);
    }

    public function destroy(VoucherNumberingRule $voucherNumberingRule): RedirectResponse
    {
        $voucherNumberingRule->delete();

        return redirect()
            ->route('setup.voucher-numbering')
            ->with('success', 'Voucher numbering rule deleted successfully.');
    }
}
