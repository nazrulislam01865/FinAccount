<?php

namespace App\Http\Controllers;

use App\Services\Setup\SetupProgressService;

class DashboardController extends Controller
{
    public function __invoke(SetupProgressService $progress)
    {
        return view('dashboard', [
            'steps' => $progress->steps(),
            'percent' => $progress->percent(),
        ]);
    }
}
