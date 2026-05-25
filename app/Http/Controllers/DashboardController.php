<?php

namespace App\Http\Controllers;

use App\Services\Dashboard\DashboardService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __construct(private readonly DashboardService $dashboardService)
    {
    }

    public function __invoke(Request $request): View
    {
        return view('dashboard', [
            'dashboard' => $this->dashboardService->forUser($request->user()),
            'currency' => config('accounting_reports.currency', 'BDT'),
        ]);
    }
}
