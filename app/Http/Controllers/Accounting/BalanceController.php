<?php

namespace App\Http\Controllers\Accounting;

use App\Http\Controllers\Controller;
use App\Services\Accounting\BalanceService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class BalanceController extends Controller
{
    public function __construct(private readonly BalanceService $service) {}

    public function index(Request $request): View
    {
        return view('balances.index', $this->service->pageData($request->user()->company_id));
    }
}
