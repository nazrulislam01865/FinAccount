<?php

namespace App\Http\Controllers\Audit;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AuditTrailController extends Controller
{
    public function __invoke(Request $request): View
    {
        return $this->index($request);
    }

    public function index(Request $request): View
    {
        $query = $this->filteredQuery($request);

        $logs = (clone $query)
            ->latest('created_at')
            ->paginate((int) $request->integer('per_page', 30))
            ->withQueryString();

        return view('audit.index', [
            'logs' => $logs,
            'filters' => $request->only(['module', 'action', 'event', 'route', 'user_id', 'date_from', 'date_to', 'search', 'per_page']),
            'modules' => $this->filterOptions('module'),
            'actions' => $this->filterOptions(Schema::hasColumn('audit_logs', 'action') ? 'action' : 'event'),
            'users' => $this->auditUsers(),
            'stats' => $this->stats($query),
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        $fileName = 'audit-log-report-' . now()->format('Ymd-His') . '.csv';
        $query = $this->filteredQuery($request)->latest('created_at');

        return response()->streamDownload(function () use ($query): void {
            $handle = fopen('php://output', 'w');

            fputcsv($handle, [
                'Time',
                'Module',
                'Action',
                'Subject',
                'Record ID',
                'User',
                'Email',
                'Route',
                'Method',
                'IP',
                'Changed Field',
                'Previous Value',
                'New Value',
                'Change Status',
                'Context',
            ]);

            $query->chunk(500, function ($logs) use ($handle): void {
                foreach ($logs as $log) {
                    $changeRows = $log->humanChangeRows();

                    if ($changeRows === []) {
                        $changeRows = [[
                            'field' => 'No value change captured',
                            'old' => '',
                            'new' => '',
                            'status' => '',
                        ]];
                    }

                    foreach ($changeRows as $row) {
                        fputcsv($handle, [
                            optional($log->created_at)->format('Y-m-d H:i:s'),
                            $log->module_label,
                            $log->action_label,
                            class_basename((string) $log->auditable_type),
                            $log->auditable_id ?: '',
                            $log->user?->name ?? 'System',
                            $log->user?->email ?? '',
                            $log->route_name ?? '',
                            $log->request_method ?? '',
                            $log->ip_address ?? '',
                            $row['field'] ?? '',
                            $row['old'] ?? '',
                            $row['new'] ?? '',
                            $row['status'] ?? '',
                            $log->humanMetadataSummary(),
                        ]);
                    }
                }
            });

            fclose($handle);
        }, $fileName, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    private function filteredQuery(Request $request): Builder
    {
        $query = AuditLog::query()->with('user:id,name,email');

        $query->when($request->filled('module'), fn (Builder $builder) => $builder->where('module', $request->input('module')));

        $action = $request->input('action', $request->input('event'));
        $query->when($action, function (Builder $builder) use ($action): void {
            $builder->where(function (Builder $where) use ($action): void {
                $where->where('event', $action);
                if (Schema::hasColumn('audit_logs', 'action')) {
                    $where->orWhere('action', $action);
                }
            });
        });

        $query->when($request->filled('user_id'), fn (Builder $builder) => $builder->where('user_id', $request->integer('user_id')));

        $query->when($request->filled('date_from'), fn (Builder $builder) => $builder->whereDate('created_at', '>=', $request->date('date_from')->toDateString()));
        $query->when($request->filled('date_to'), fn (Builder $builder) => $builder->whereDate('created_at', '<=', $request->date('date_to')->toDateString()));

        if ($request->filled('route') && Schema::hasColumn('audit_logs', 'route_name')) {
            $route = (string) $request->input('route');
            $query->where('route_name', 'like', '%' . $route . '%');
        }

        if ($request->filled('search')) {
            $search = trim((string) $request->input('search'));
            $query->where(function (Builder $where) use ($search): void {
                $where->where('auditable_type', 'like', '%' . $search . '%')
                    ->orWhere('event', 'like', '%' . $search . '%');

                if (ctype_digit($search)) {
                    $where->orWhere('auditable_id', (int) $search);
                }

                if (Schema::hasColumn('audit_logs', 'module')) {
                    $where->orWhere('module', 'like', '%' . $search . '%');
                }

                if (Schema::hasColumn('audit_logs', 'action')) {
                    $where->orWhere('action', 'like', '%' . $search . '%');
                }

                if (Schema::hasColumn('audit_logs', 'request_url')) {
                    $where->orWhere('request_url', 'like', '%' . $search . '%');
                }

                $where->orWhereHas('user', function (Builder $userQuery) use ($search): void {
                    $userQuery->where('name', 'like', '%' . $search . '%')
                        ->orWhere('email', 'like', '%' . $search . '%');
                });
            });
        }

        return $query;
    }

    /**
     * @return array<string, int>
     */
    private function stats(Builder $query): array
    {
        return [
            'total' => (clone $query)->count(),
            'today' => (clone $query)->whereDate('created_at', now()->toDateString())->count(),
            'posting' => (clone $query)->where(function (Builder $builder): void {
                $builder->whereIn('event', ['voucher_posted', 'opening_balance_posted', 'voucher_submitted', 'voucher_reversed']);
                if (Schema::hasColumn('audit_logs', 'action')) {
                    $builder->orWhereIn('action', ['voucher_posted', 'opening_balance_posted', 'voucher_submitted', 'voucher_reversed']);
                }
            })->count(),
            'security' => Schema::hasColumn('audit_logs', 'module')
                ? (clone $query)->where('module', 'SecurityAccess')->count()
                : 0,
        ];
    }

    /**
     * @return array<int, string>
     */
    private function filterOptions(string $column): array
    {
        if (! Schema::hasColumn('audit_logs', $column)) {
            return [];
        }

        return AuditLog::query()
            ->whereNotNull($column)
            ->where($column, '!=', '')
            ->select($column)
            ->distinct()
            ->orderBy($column)
            ->pluck($column)
            ->all();
    }

    private function auditUsers()
    {
        $userIds = AuditLog::query()
            ->whereNotNull('user_id')
            ->distinct()
            ->pluck('user_id')
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->all();

        if ($userIds === []) {
            return collect();
        }

        return User::query()
            ->whereIn('id', $userIds)
            ->orderBy('name')
            ->get(['id', 'name', 'email']);
    }
}
