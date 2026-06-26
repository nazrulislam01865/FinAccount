<?php

namespace App\Http\Controllers\Accounting;

use App\Http\Controllers\Controller;
use App\Models\AccountingRule;
use App\Models\ChartOfAccount;
use App\Models\DocumentSequence;
use App\Models\JournalEntry;
use App\Models\MoneyAccount;
use App\Models\Party;
use App\Models\Transaction;
use App\Models\TransactionHead;
use App\Services\Accounting\TransactionPostingService;
use App\Services\Dashboard\DashboardService;
use Database\Seeders\HisebGhorDemoSeeder;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function __construct(private readonly DashboardService $dashboardService) {}

    public function index(Request $request): View
    {
        return view('dashboard.index', $this->dashboardService->summary(
            $request->user()->company_id,
            $request->string('period')->toString(),
        ));
    }

    public function resetDemo(Request $request): RedirectResponse
    {
        $user = $request->user();
        $companyId = $user->company_id;
        abort_unless($companyId && $user->company, 422, 'Your user account is not connected to a company.');

        DB::transaction(function () use ($companyId, $user): void {
            JournalEntry::query()->where('company_id', $companyId)->delete();
            Transaction::query()->where('company_id', $companyId)->delete();
            TransactionHead::query()->where('company_id', $companyId)->delete();
            AccountingRule::query()->where('company_id', $companyId)->delete();
            Party::query()->where('company_id', $companyId)->delete();
            MoneyAccount::query()->where('company_id', $companyId)->delete();
            DocumentSequence::query()->where('company_id', $companyId)->delete();
            foreach ([3, 2, 1] as $level) {
                ChartOfAccount::query()
                    ->where('company_id', $companyId)
                    ->where('level', $level)
                    ->delete();
            }

            app(HisebGhorDemoSeeder::class)->seedCompany(
                $user->company,
                $user,
                app(TransactionPostingService::class),
            );
        }, attempts: 5);

        return redirect()
            ->route('dashboard')
            ->with('success', 'Sample data restored');
    }
}
