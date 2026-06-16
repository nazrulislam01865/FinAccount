<?php

namespace App\Http\Controllers\Accounting;

use App\Http\Controllers\Controller;
use App\Models\AccountingOption;
use App\Services\Accounting\AccountingOptionService;
use App\Services\Accounting\JournalEntryService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class JournalEntryController extends Controller
{
    public function __construct(
        private readonly JournalEntryService $service,
        private readonly AccountingOptionService $optionService,
    ) {}

    public function index(Request $request): View
    {
        return view('journal-entries.index', [
            'journalLines' => $this->service->linesForCompany($request->user()->company_id),
            'categoryLabels' => $this->optionService->labels(AccountingOption::GROUP_TRANSACTION_CATEGORY),
        ]);
    }
}
