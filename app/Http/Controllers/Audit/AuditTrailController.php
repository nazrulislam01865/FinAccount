<?php

namespace App\Http\Controllers\Audit;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

class AuditTrailController extends Controller
{
    public function __invoke(Request $request): View
    {
        $logs = AuditLog::query()
            ->with('user:id,name,email')
            ->when($request->filled('module'), fn ($query) => $query->where('module', $request->input('module')))
            ->when($request->filled('event'), fn ($query) => $query->where(function ($where) use ($request) {
                $where->where('event', $request->input('event'))
                    ->orWhere('action', $request->input('event'));
            }))
            ->when($request->filled('route') && Schema::hasColumn('audit_logs', 'route_name'), fn ($query) => $query->where('route_name', 'like', '%' . $request->input('route') . '%'))
            ->latest('created_at')
            ->paginate(30)
            ->withQueryString();

        return view('audit.index', [
            'logs' => $logs,
            'filters' => $request->only(['module', 'event', 'route']),
        ]);
    }
}
