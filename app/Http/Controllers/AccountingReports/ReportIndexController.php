<?php

namespace App\Http\Controllers\AccountingReports;

use App\Http\Controllers\Controller;
use Illuminate\View\View;

class ReportIndexController extends Controller
{
    public function __invoke(): View
    {
        return view('accounting_reports.index');
    }
}
