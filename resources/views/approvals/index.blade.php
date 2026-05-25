@extends('layouts.app')
@section('title', 'Approvals | HisebGhor')
@section('content')
<div class="page-title">
    <div>
        <span class="page-label">Approval Workflow</span>
        <h2>Pending Transaction Approvals</h2>
        <p>Review submitted vouchers before they affect ledgers and reports.</p>
    </div>
</div>

@if(session('success'))
    <div class="card info-card" style="margin-bottom:16px"><strong>{{ session('success') }}</strong></div>
@endif

@if($errors->any())
    <div class="card info-card" style="margin-bottom:16px;border-color:#fecaca;background:#fef2f2;color:#991b1b">
        <strong>{{ $errors->first() }}</strong>
    </div>
@endif

<div class="card table-card">
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Voucher</th>
                    <th>Transaction Head</th>
                    <th>Party</th>
                    <th>Amount</th>
                    <th>Status</th>
                    <th>Submitted</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                @forelse($pendingVouchers as $voucher)
                    <tr>
                        <td>{{ optional($voucher->voucher_date)->format('d M Y') }}</td>
                        <td><span class="code">{{ $voucher->voucher_number }}</span><br><small class="muted">{{ $voucher->voucher_type }}</small></td>
                        <td>{{ $voucher->transactionHead?->name ?? '—' }}</td>
                        <td>{{ $voucher->party?->party_name ?? '—' }}</td>
                        <td class="strong">{{ $currency }} {{ number_format((float) $voucher->amount, 2) }}</td>
                        <td><span class="badge badge-warning">{{ $voucher->status }}</span></td>
                        <td>{{ optional($voucher->submitted_at)->format('d M Y h:i A') }}</td>
                        <td>
                            <div class="action-cell">
                                <form method="POST" action="{{ route('approvals.approve', $voucher) }}">
                                    @csrf
                                    <button type="submit" class="button btn-primary">Approve</button>
                                </form>
                                <form method="POST" action="{{ route('approvals.reject', $voucher) }}">
                                    @csrf
                                    <button type="submit" class="button btn-ghost">Reject</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr data-empty="true"><td colspan="8" class="muted">No transactions are waiting for approval.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="table-footer">{{ $pendingVouchers->links() }}</div>
</div>
@endsection
