@extends('layouts.app')
@section('title', 'Audit Trail | HisebGhor')
@section('content')
<div class="page-title">
    <div>
        <span class="page-label">Audit Trail</span>
        <h2>System Activity Log</h2>
        <p>Track sensitive setup, rule, voucher, user, and permission changes.</p>
    </div>
</div>

<div class="card table-card">
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Time</th>
                    <th>Module</th>
                    <th>Action</th>
                    <th>Record</th>
                    <th>User</th>
                    <th>Change Summary</th>
                </tr>
            </thead>
            <tbody>
                @forelse($logs as $log)
                    <tr>
                        <td>{{ optional($log->created_at)->format('d M Y h:i A') }}</td>
                        <td>{{ $log->module ?? class_basename($log->auditable_type) }}</td>
                        <td><span class="badge badge-neutral">{{ $log->action ?? $log->event }}</span></td>
                        <td>#{{ $log->auditable_id }}</td>
                        <td>{{ $log->user?->name ?? 'System' }}</td>
                        <td><small>{{ \Illuminate\Support\Str::limit(json_encode($log->new_values ?: $log->old_values), 160) }}</small></td>
                    </tr>
                @empty
                    <tr data-empty="true"><td colspan="6" class="muted">No audit log entries found.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="table-footer">{{ $logs->links() }}</div>
</div>
@endsection
