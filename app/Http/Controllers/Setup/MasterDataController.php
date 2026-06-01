<?php

namespace App\Http\Controllers\Setup;

use App\Http\Controllers\Controller;
use App\Http\Requests\MasterBusinessTypeRequest;
use App\Http\Requests\MasterCurrencyRequest;
use App\Http\Requests\MasterFinancialYearRequest;
use App\Http\Requests\MasterLedgerTypeRequest;
use App\Http\Requests\MasterPartyTypeRequest;
use App\Http\Requests\MasterSettlementTypeRequest;
use App\Models\BusinessType;
use App\Models\ChartOfAccount;
use App\Models\Company;
use App\Models\Currency;
use App\Models\FinancialYear;
use App\Models\LedgerType;
use App\Models\PartyType;
use App\Models\SettlementType;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

class MasterDataController extends Controller
{
    /**
     * Send the old Master Data URL to the first Master Data sub-page.
     */
    public function index(): RedirectResponse
    {
        return redirect()->route('setup.master-data.business-types');
    }

    /**
     * Show Business Types as a separate Master Data sub-page.
     */
    public function businessTypes(): View
    {
        return $this->masterDataView('business-types');
    }

    /**
     * Show Currencies as a separate Master Data sub-page.
     */
    public function currencies(): View
    {
        return $this->masterDataView('currencies');
    }

    /**
     * Show Settlement Types as a separate Master Data sub-page.
     */
    public function settlementTypes(): View
    {
        return $this->masterDataView('settlement-types');
    }

    /**
     * Show Party Types as a separate Master Data sub-page.
     */
    public function partyTypes(): View
    {
        return $this->masterDataView('party-types');
    }

    /**
     * Show Ledger Types as a separate Master Data sub-page.
     */
    public function ledgerTypes(): View
    {
        return $this->masterDataView('ledger-types');
    }

    /**
     * Show Financial Years as a separate Master Data sub-page.
     */
    public function financialYears(): View
    {
        return $this->masterDataView('financial-years');
    }

    private function masterDataView(string $activePage): View
    {
        $tabs = $this->masterDataTabs();
        $ledgerTypeUsageCounts = $this->ledgerTypeUsageCountsByName();

        return view('setup.master-data', [
            'activeMasterDataPage' => $activePage,
            'activeMasterDataTab' => $tabs[$activePage],
            'masterDataTabs' => $tabs,
            'ledgerTypeUsageCounts' => $ledgerTypeUsageCounts,
            'businessTypes' => BusinessType::query()
                ->orderByDesc('is_default')
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get(),
            'currencies' => Currency::query()
                ->orderByDesc('is_default')
                ->orderBy('sort_order')
                ->orderBy('code')
                ->get(),
            'settlementTypes' => SettlementType::query()
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get(),
            'partyTypes' => PartyType::query()
                ->with('defaultLedger')
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get(),
            'ledgerTypes' => LedgerType::query()
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get(),
            'financialYears' => FinancialYear::query()
                ->orderByDesc('is_current')
                ->orderByDesc('is_active')
                ->orderByRaw("CASE status WHEN 'Open' THEN 1 WHEN 'Locked' THEN 2 WHEN 'Closed' THEN 3 ELSE 4 END")
                ->orderByDesc('start_date')
                ->get(),
            'ledgerAccounts' => ChartOfAccount::query()
                ->where('status', 'Active')
                ->orderBy('account_code')
                ->get(),
        ]);
    }

    private function masterDataTabs(): array
    {
        return [
            'business-types' => [
                'label' => 'Business Types',
                'route' => 'setup.master-data.business-types',
                'description' => 'Values used in Company Setup business type dropdown.',
                'count' => BusinessType::query()->count(),
            ],
            'currencies' => [
                'label' => 'Currencies',
                'route' => 'setup.master-data.currencies',
                'description' => 'Values used in Company Setup and transaction currency dropdowns.',
                'count' => Currency::query()->count(),
            ],
            'settlement-types' => [
                'label' => 'Settlement Types',
                'route' => 'setup.master-data.settlement-types',
                'description' => 'Values used in transaction heads, Accounting Rules Setup, and transactions.',
                'count' => SettlementType::query()->count(),
            ],
            'party-types' => [
                'label' => 'Party Types',
                'route' => 'setup.master-data.party-types',
                'description' => 'Values used in Party / Person Setup and transaction defaults.',
                'count' => PartyType::query()->count(),
            ],
            'ledger-types' => [
                'label' => 'Ledger Types',
                'route' => 'setup.master-data.ledger-types',
                'description' => 'Values used by Chart of Accounts ledger classification dropdown.',
                'count' => LedgerType::query()->count(),
            ],
            'financial-years' => [
                'label' => 'Financial Years',
                'route' => 'setup.master-data.financial-years',
                'description' => 'Values used in opening balance and voucher numbering setup.',
                'count' => FinancialYear::query()->count(),
            ],
        ];
    }

    /**
     * Create a business type used by Company Setup.
     */
    public function storeBusinessType(MasterBusinessTypeRequest $request): JsonResponse
    {
        $businessType = $this->saveBusinessType(new BusinessType(), $request->validated());

        return $this->saved('Business type saved successfully.', $businessType, 'setup.master-data.business-types');
    }

    /**
     * Update a business type used by Company Setup.
     */
    public function updateBusinessType(
        MasterBusinessTypeRequest $request,
        BusinessType $businessType
    ): JsonResponse {
        $businessType = $this->saveBusinessType($businessType, $request->validated());

        return $this->saved('Business type updated successfully.', $businessType, 'setup.master-data.business-types');
    }

    /**
     * Remove an unused business type from the database.
     */
    public function destroyBusinessType(Request $request, BusinessType $businessType): JsonResponse|RedirectResponse
    {
        if (DB::table('companies')->where('business_type_id', $businessType->id)->exists()) {
            return $this->blocked($request, 'This business type is used by Company Setup and cannot be deleted.', 'setup.master-data.business-types');
        }

        $businessType->delete();

        return $this->deleted($request, 'Business type deleted successfully.', 'setup.master-data.business-types');
    }

    /**
     * Create a currency used by Company Setup and currency dropdowns.
     */
    public function storeCurrency(MasterCurrencyRequest $request): JsonResponse
    {
        $currency = $this->saveCurrency(new Currency(), $request->validated());

        return $this->saved('Currency saved successfully.', $currency, 'setup.master-data.currencies');
    }

    /**
     * Update a currency used by Company Setup and currency dropdowns.
     */
    public function updateCurrency(
        MasterCurrencyRequest $request,
        Currency $currency
    ): JsonResponse {
        $currency = $this->saveCurrency($currency, $request->validated());

        return $this->saved('Currency updated successfully.', $currency, 'setup.master-data.currencies');
    }

    /**
     * Remove an unused currency from the database.
     */
    public function destroyCurrency(Request $request, Currency $currency): JsonResponse|RedirectResponse
    {
        if (DB::table('companies')->where('currency_id', $currency->id)->exists()) {
            return $this->blocked($request, 'This currency is used by Company Setup and cannot be deleted.', 'setup.master-data.currencies');
        }

        $currency->delete();

        return $this->deleted($request, 'Currency deleted successfully.', 'setup.master-data.currencies');
    }

    /**
     * Create a settlement type used by transaction heads, mappings, and transaction entry.
     */
    public function storeSettlementType(MasterSettlementTypeRequest $request): JsonResponse
    {
        $settlementType = SettlementType::query()->create($request->validated());

        return $this->saved('Settlement type saved successfully.', $settlementType, 'setup.master-data.settlement-types');
    }

    /**
     * Update a settlement type without affecting the table design or existing relations.
     */
    public function updateSettlementType(
        MasterSettlementTypeRequest $request,
        SettlementType $settlementType
    ): JsonResponse {
        $settlementType->update($request->validated());

        return $this->saved('Settlement type updated successfully.', $settlementType, 'setup.master-data.settlement-types');
    }

    /**
     * Remove an unused settlement type from the database.
     */
    public function destroySettlementType(Request $request, SettlementType $settlementType): JsonResponse|RedirectResponse
    {
        if ($settlementType->transactionHeads()->exists()) {
            return $this->blocked($request, 'This settlement type is used by transaction heads and cannot be deleted.', 'setup.master-data.settlement-types');
        }

        if (DB::table('ledger_mapping_rules')->where('settlement_type_id', $settlementType->id)->exists()) {
            return $this->blocked($request, 'This settlement type is used by accounting rules and cannot be deleted.', 'setup.master-data.settlement-types');
        }

        $settlementType->delete();

        return $this->deleted($request, 'Settlement type deleted successfully.', 'setup.master-data.settlement-types');
    }

    /**
     * Create a party type for Party / Person Setup and transaction head defaults.
     */
    public function storePartyType(MasterPartyTypeRequest $request): JsonResponse
    {
        $partyType = PartyType::query()->create($request->validated());

        return $this->saved('Party type saved successfully.', $partyType, 'setup.master-data.party-types');
    }

    /**
     * Update a party type and optional default ledger link.
     */
    public function updatePartyType(MasterPartyTypeRequest $request, PartyType $partyType): JsonResponse
    {
        $partyType->update($request->validated());

        return $this->saved('Party type updated successfully.', $partyType, 'setup.master-data.party-types');
    }

    /**
     * Remove an unused party type from the database.
     */
    public function destroyPartyType(Request $request, PartyType $partyType): JsonResponse|RedirectResponse
    {
        if (DB::table('parties')->where('party_type_id', $partyType->id)->exists()) {
            return $this->blocked($request, 'This party type is used by parties and cannot be deleted.', 'setup.master-data.party-types');
        }

        if (DB::table('transaction_heads')->where('default_party_type_id', $partyType->id)->exists()) {
            return $this->blocked($request, 'This party type is used by transaction heads and cannot be deleted.', 'setup.master-data.party-types');
        }

        $partyType->delete();

        return $this->deleted($request, 'Party type deleted successfully.', 'setup.master-data.party-types');
    }

    /**
     * Create a ledger type used by Chart of Accounts setup.
     */
    public function storeLedgerType(MasterLedgerTypeRequest $request): JsonResponse
    {
        $ledgerType = LedgerType::query()->create($request->validated());

        return $this->saved('Ledger type saved successfully.', $ledgerType, 'setup.master-data.ledger-types');
    }

    /**
     * Update a ledger type used by Chart of Accounts setup.
     */
    public function updateLedgerType(MasterLedgerTypeRequest $request, LedgerType $ledgerType): JsonResponse
    {
        $ledgerType->update($request->validated());

        return $this->saved('Ledger type updated successfully.', $ledgerType, 'setup.master-data.ledger-types');
    }

    /**
     * Remove an unused ledger type from master data.
     */
    public function destroyLedgerType(Request $request, LedgerType $ledgerType): JsonResponse|RedirectResponse
    {
        if ($ledgerType->isProtectedSystemType()) {
            return $this->blocked(
                $request,
                'This is a protected accounting ledger type used by the posting engine and reports. Set the status to Inactive if it should not be available for new Chart of Accounts records.',
                'setup.master-data.ledger-types'
            );
        }

        $usageCount = $this->ledgerTypeUsageCount($ledgerType->name);

        if ($usageCount > 0) {
            return $this->blocked(
                $request,
                "This ledger type is used by {$usageCount} setup/rule record(s). Reassign those records first, or set the ledger type status to Inactive instead of deleting it.",
                'setup.master-data.ledger-types'
            );
        }

        $ledgerType->delete();

        return $this->deleted($request, 'Ledger type deleted successfully.', 'setup.master-data.ledger-types');
    }

    /**
     * Create a financial year used by opening balances and voucher numbering.
     */
    public function storeFinancialYear(MasterFinancialYearRequest $request): JsonResponse
    {
        $financialYear = $this->saveFinancialYear(new FinancialYear(), $request->validated(), $request->user()?->id);

        return $this->saved('Financial year saved successfully.', $financialYear, 'setup.master-data.financial-years');
    }

    /**
     * Update a financial year and keep only one active year when requested.
     */
    public function updateFinancialYear(
        MasterFinancialYearRequest $request,
        FinancialYear $financialYear
    ): JsonResponse {
        $financialYear = $this->saveFinancialYear($financialYear, $request->validated(), $request->user()?->id);

        return $this->saved('Financial year updated successfully.', $financialYear, 'setup.master-data.financial-years');
    }

    /**
     * Mark a financial year as the single current/default posting year for the company.
     */
    public function setCurrentFinancialYear(Request $request, FinancialYear $financialYear): JsonResponse|RedirectResponse
    {
        $company = Company::query()->first();
        $userId = $request->user()?->id;

        DB::transaction(function () use ($financialYear, $company, $userId) {
            FinancialYear::query()
                ->when($company?->id, function ($query) use ($company) {
                    $query->where(function ($where) use ($company) {
                        $where->where('company_id', $company->id)
                            ->orWhereNull('company_id');
                    });
                })
                ->update([
                    'is_active' => false,
                    'is_current' => false,
                    'updated_by' => $userId,
                ]);

            $financialYear->forceFill([
                'company_id' => $company?->id ?: $financialYear->company_id,
                'status' => FinancialYear::STATUS_OPEN,
                'is_active' => true,
                'is_current' => true,
                'updated_by' => $userId,
            ])->save();

            if ($company) {
                $company->forceFill([
                    'default_financial_year_id' => $financialYear->id,
                    'financial_year_start' => $financialYear->start_date,
                    'financial_year_end' => $financialYear->end_date,
                    'updated_by' => $userId,
                ])->save();
            }
        });

        return $this->statusChanged($request, 'Financial year set as current successfully.');
    }

    /**
     * Close a financial year so posting is blocked until an authorized user reopens it.
     */
    public function closeFinancialYear(Request $request, FinancialYear $financialYear): JsonResponse|RedirectResponse
    {
        $financialYear->forceFill([
            'status' => FinancialYear::STATUS_CLOSED,
            'is_active' => false,
            'is_current' => false,
            'updated_by' => $request->user()?->id,
        ])->save();

        $company = Company::query()->where('default_financial_year_id', $financialYear->id)->first();

        if ($company) {
            $company->forceFill(['default_financial_year_id' => null])->save();
        }

        return $this->statusChanged($request, 'Financial year closed successfully. Posting is now blocked for this year.');
    }

    /**
     * Reopen a closed or locked financial year. Reopen does not automatically make it current.
     */
    public function reopenFinancialYear(Request $request, FinancialYear $financialYear): JsonResponse|RedirectResponse
    {
        $financialYear->forceFill([
            'status' => FinancialYear::STATUS_OPEN,
            'updated_by' => $request->user()?->id,
        ])->save();

        return $this->statusChanged($request, 'Financial year reopened successfully. Use Set Current if this should be the default posting year.');
    }

    /**
     * Remove an unused financial year from the database.
     */
    public function destroyFinancialYear(Request $request, FinancialYear $financialYear): JsonResponse|RedirectResponse
    {
        if (DB::table('opening_balances')->where('financial_year_id', $financialYear->id)->exists()) {
            return $this->blocked($request, 'This financial year is used by opening balances and cannot be deleted.', 'setup.master-data.financial-years');
        }

        if (DB::table('voucher_numbering_rules')->where('financial_year_id', $financialYear->id)->exists()) {
            return $this->blocked($request, 'This financial year is used by voucher numbering and cannot be deleted.', 'setup.master-data.financial-years');
        }

        if (Schema::hasColumn('companies', 'default_financial_year_id') && DB::table('companies')->where('default_financial_year_id', $financialYear->id)->exists()) {
            return $this->blocked($request, 'This financial year is selected as the company default/current year and cannot be deleted.', 'setup.master-data.financial-years');
        }

        $financialYear->forceDelete();

        return $this->deleted($request, 'Financial year deleted successfully.', 'setup.master-data.financial-years');
    }

    /**
     * Persist business type data and keep only one default when requested.
     */
    private function saveBusinessType(BusinessType $businessType, array $data): BusinessType
    {
        return DB::transaction(function () use ($businessType, $data) {
            if ($data['is_default']) {
                BusinessType::query()->update(['is_default' => false]);
            }

            $businessType->fill($data);
            $businessType->save();

            return $businessType->fresh();
        });
    }

    /**
     * Persist currency data and keep only one default when requested.
     */
    private function saveCurrency(Currency $currency, array $data): Currency
    {
        return DB::transaction(function () use ($currency, $data) {
            if ($data['is_default']) {
                Currency::query()->update(['is_default' => false]);
            }

            $currency->fill($data);
            $currency->save();

            return $currency->fresh();
        });
    }

    /**
     * Persist financial-year data and enforce one active financial year at a time.
     */
    private function saveFinancialYear(FinancialYear $financialYear, array $data, ?int $userId): FinancialYear
    {
        return DB::transaction(function () use ($financialYear, $data, $userId) {
            $company = Company::query()->first();
            $isNew = ! $financialYear->exists;

            if ($data['is_current']) {
                FinancialYear::query()
                    ->when($company?->id, function ($query) use ($company) {
                        $query->where(function ($where) use ($company) {
                            $where->where('company_id', $company->id)
                                ->orWhereNull('company_id');
                        });
                    })
                    ->update([
                        'is_active' => false,
                        'is_current' => false,
                        'updated_by' => $userId,
                    ]);
            }

            $financialYear->fill([
                // During first-time setup Company may not exist yet.
                // So company_id must remain nullable until Company Setup assigns it.
                'company_id' => $company?->id,
                'name' => $data['name'],
                'start_date' => $data['start_date'],
                'end_date' => $data['end_date'],
                'lock_date' => $data['lock_date'] ?? null,
                'is_active' => (bool) $data['is_current'],
                'is_current' => (bool) $data['is_current'],
                'status' => $data['status'],
                'updated_by' => $userId,
            ]);

            if ($isNew) {
                $financialYear->created_by = $userId;
            }

            $financialYear->save();

            if (($data['is_current'] ?? false) && $company) {
                $company->forceFill([
                    'default_financial_year_id' => $financialYear->id,
                    'financial_year_start' => $financialYear->start_date,
                    'financial_year_end' => $financialYear->end_date,
                    'updated_by' => $userId,
                ])->save();
            }

            return $financialYear->fresh();
        });
    }
    


    /**
     * Count ledger-type references across setup tables that store ledger type names.
     * This protects accounting integrity while allowing unused optional/custom types to be deleted.
     */
    private function ledgerTypeUsageCountsByName(): array
    {
        if (! Schema::hasTable('ledger_types')) {
            return [];
        }

        $counts = [];

        foreach (LedgerType::query()->pluck('name') as $name) {
            $counts[$name] = $this->ledgerTypeUsageCount($name);
        }

        return $counts;
    }

    private function ledgerTypeUsageCount(string $ledgerTypeName): int
    {
        $checks = [
            ['chart_of_accounts', 'ledger_type'],
            ['accounting_rule_lines', 'allowed_ledger_type'],
            ['ledger_mapping_rules', 'allowed_counter_ledger_type'],
        ];

        $total = 0;

        foreach ($checks as [$table, $column]) {
            if (! Schema::hasTable($table) || ! Schema::hasColumn($table, $column)) {
                continue;
            }

            $total += (int) DB::table($table)
                ->where($column, $ledgerTypeName)
                ->count();
        }

        return $total;
    }

    private function statusChanged(Request $request, string $message): JsonResponse|RedirectResponse
    {
        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'message' => $message,
                'redirect' => route('setup.master-data.financial-years'),
            ]);
        }

        return redirect()->route('setup.master-data.financial-years')->with('status', $message);
    }

    private function saved(string $message, object $model, string $redirectRoute): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => $model,
            'redirect' => route($redirectRoute),
        ]);
    }

    private function deleted(Request $request, string $message, string $redirectRoute = 'setup.master-data.business-types'): JsonResponse|RedirectResponse
    {
        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'message' => $message,
            ]);
        }

        return redirect()->route($redirectRoute)->with('status', $message);
    }

    private function blocked(Request $request, string $message, string $redirectRoute = 'setup.master-data.business-types'): JsonResponse|RedirectResponse
    {
        if ($request->expectsJson()) {
            return response()->json([
                'success' => false,
                'message' => $message,
            ], 409);
        }

        return redirect()->route($redirectRoute)->withErrors(['delete' => $message]);
    }
}
