<?php

namespace App\Http\Controllers\Setup;

use App\Http\Controllers\Controller;
use App\Http\Requests\TransactionHeadRequest;
use App\Services\Setup\TransactionHeadService;

class TransactionHeadController extends Controller
{
    public function store(TransactionHeadRequest $request, TransactionHeadService $service)
    {
        $head = $service->create($request->validated(), $request->user()?->id);

        return response()->json([
            'success' => true,
            'message' => 'Transaction head saved successfully.',
            'data' => $head,
        ], 201);
    }
}
