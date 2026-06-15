<?php

namespace App\Http\Controllers\Accounting;

use App\Http\Controllers\Controller;
use App\Services\Accounting\BasicStatementService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class BasicStatementController extends Controller
{
    public function __construct(private readonly BasicStatementService $service) {}

    public function index(Request $request): View
    {
        return view('basic-statements.index', [
            'statement' => $this->service->summary($request->user()->company_id),
        ]);
    }
}
