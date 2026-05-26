@extends('layouts.app')

@section('title', 'Transaction List')

@push('styles')
<style>
    .txn-list-page .page-title{display:flex;align-items:flex-start;justify-content:space-between;gap:20px;margin-bottom:18px}.txn-list-page .page-title h2{margin:0 0 8px;font-size:30px;line-height:1.1;letter-spacing:-.035em}.txn-list-page .page-title p{margin:0;color:var(--muted);font-size:15px}.txn-list-page .quick-actions{display:flex;gap:10px;flex-wrap:wrap}.txn-list-page a.btn-primary,.txn-list-page a.btn-outline,.txn-list-page a.btn-ghost{min-height:44px;display:inline-flex;align-items:center;justify-content:center;gap:8px;border-radius:11px;padding:12px 16px;font-weight:850;font-size:14px;text-decoration:none}.txn-list-page .stats{display:grid;grid-template-columns:repeat(4,1fr);gap:16px;margin-bottom:18px}.txn-list-page .stat{padding:18px}.txn-list-page .stat span{display:block;color:var(--muted);font-size:13px;margin-bottom:8px}.txn-list-page .stat strong{font-size:24px;letter-spacing:-.03em}.txn-list-page .stat small{display:block;margin-top:6px;color:var(--muted);font-size:12px}.txn-list-page .green{color:#067647}.txn-list-page .red{color:#dc2626}.txn-list-page .blue{color:var(--primary)}.txn-list-page .orange{color:#b54708}
    .txn-list-page .filters{display:grid;grid-template-columns:repeat(auto-fit,minmax(210px,1fr));gap:14px;padding:18px;align-items:end;margin-bottom:18px}.txn-list-page .filters>*{min-width:0}.txn-list-page .field{position:relative}.txn-list-page .date-range-field{grid-column:span 2;min-width:0}.txn-list-page .date-range-inputs{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:10px;min-width:0}.txn-list-page .field.search-field span{position:absolute;left:14px;top:38px;color:var(--muted)}.txn-list-page input,.txn-list-page select{width:100%;min-height:44px;border:1px solid #d8dee9;border-radius:11px;background:#fff;padding:11px 13px;font-size:14px;color:#101828;outline:none;transition:.16s ease}.txn-list-page .search-field input{padding-left:42px}.txn-list-page input:focus,.txn-list-page select:focus{border-color:var(--primary);box-shadow:0 0 0 4px rgba(37,99,235,.1)}.txn-list-page label{display:block;margin-bottom:7px;font-size:13px;font-weight:750;color:#344054}
    .txn-list-page .table-card{overflow:hidden}.txn-list-page .table-head{display:flex;align-items:center;justify-content:space-between;padding:18px 20px;border-bottom:1px solid var(--line)}.txn-list-page .table-head h3{margin:0;font-size:17px}.txn-list-page .table-head span{font-size:13px;color:var(--muted)}.txn-list-page .table-wrap{overflow-x:scroll;scrollbar-gutter:stable both-edges}.txn-list-page table{width:100%;border-collapse:collapse;font-size:14px;min-width:1100px}.txn-list-page thead th{background:#fbfcfd;color:#344054;font-size:12px;text-align:left;font-weight:850;padding:15px 18px;border-bottom:1px solid var(--line);white-space:nowrap}.txn-list-page tbody td{padding:15px 18px;border-bottom:1px solid #edf0f3;color:#344054;vertical-align:middle}.txn-list-page tbody tr:hover{background:#f9fbff}.txn-list-page tbody tr.selected{background:#eef4ff}.txn-list-page .voucher{font-weight:900;color:#1d2939}.txn-list-page .head-name{font-weight:750;color:#101828}.txn-list-page .amount{font-weight:900;text-align:right}.txn-list-page .badge{display:inline-flex;align-items:center;border-radius:999px;padding:5px 10px;font-size:12px;font-weight:850;white-space:nowrap}.txn-list-page .badge-payment{background:var(--danger-soft);color:#b42318}.txn-list-page .badge-receipt{background:var(--success-soft);color:#067647}.txn-list-page .badge-due{background:var(--warning-soft);color:#b54708}.txn-list-page .badge-advance{background:var(--purple-soft);color:var(--purple)}.txn-list-page .badge-adjustment{background:#eef2f6;color:#475467}.txn-list-page .badge-posted{background:var(--success-soft);color:#067647}.txn-list-page .badge-draft{background:#f2f4f7;color:#475467}.txn-list-page .badge-pending{background:var(--warning-soft);color:#b54708}.txn-list-page .badge-reversed{background:var(--danger-soft);color:#b42318}.txn-list-page .muted{color:var(--muted)}.txn-list-page .action-cell{display:flex;align-items:center;gap:8px;justify-content:flex-end}.txn-list-page .icon-btn{width:32px;height:32px;min-height:32px;padding:0;display:grid;place-items:center;border-radius:8px;background:transparent;color:#475467;border:none;text-decoration:none;cursor:pointer}.txn-list-page .icon-btn:hover{background:#eef4ff;color:var(--primary)}.txn-list-page .icon-btn.is-danger:hover{background:#fee4e2;color:#b42318}.txn-list-page .table-footer{display:flex;align-items:center;justify-content:space-between;padding:16px 18px;border-top:1px solid var(--line);color:var(--muted);font-size:13px}.txn-list-page .pagination{display:flex;gap:8px}.txn-list-page .page-btn{width:34px;height:34px;min-height:34px;padding:0;border-radius:9px;background:#fff;color:#475467;border:1px solid var(--line);display:grid;place-items:center;text-decoration:none;font-weight:850}.txn-list-page .page-btn.active{background:var(--primary);color:#fff;border-color:var(--primary)}.txn-list-page .page-btn.disabled{opacity:.45;pointer-events:none}.txn-list-page .empty-state{padding:34px;text-align:center;color:var(--muted)}
    .txn-drawer{position:fixed;right:-470px;top:0;width:470px;max-width:100%;height:100vh;background:#fff;border-left:1px solid var(--line);box-shadow:-18px 0 45px rgba(16,24,40,.12);z-index:60;transition:.24s ease;display:flex;flex-direction:column}.txn-drawer.open{right:0}.txn-drawer-head{padding:22px;border-bottom:1px solid var(--line);display:flex;align-items:flex-start;justify-content:space-between}.txn-drawer-head h3{margin:0 0 5px;font-size:20px}.txn-drawer-head p{margin:0;color:var(--muted);font-size:13px}.txn-close{font-size:24px;color:var(--muted);cursor:pointer;border:none;background:transparent;min-height:auto;padding:0}.txn-drawer-body{padding:20px;overflow:auto;display:grid;gap:18px}.txn-detail-card{border:1px solid var(--line);border-radius:14px;padding:16px}.txn-detail-card h4{margin:0 0 12px;font-size:15px}.txn-detail-row{display:flex;justify-content:space-between;gap:12px;margin:10px 0;font-size:14px}.txn-detail-row span:first-child{color:var(--muted)}.txn-ledger-table{width:100%;min-width:0!important;font-size:13px}.txn-ledger-table th,.txn-ledger-table td{padding:11px;border-bottom:1px solid #edf0f3;white-space:normal}.txn-ledger-table th:nth-child(2),.txn-ledger-table th:nth-child(3),.txn-ledger-table td:nth-child(2),.txn-ledger-table td:nth-child(3){text-align:right;white-space:nowrap}.txn-drawer-actions{padding:18px 20px;border-top:1px solid var(--line);display:grid;grid-template-columns:1fr 1fr;gap:10px}.txn-toast{position:fixed;right:26px;bottom:26px;padding:14px 18px;border-radius:14px;background:#101828;color:white;font-size:14px;box-shadow:var(--shadow);display:none;z-index:80}.txn-toast.show{display:block;animation:pop .22s ease}.txn-print-only{display:none}
    @media(max-width:1240px){.txn-list-page .stats{grid-template-columns:repeat(2,1fr)}}@media(max-width:880px){.txn-list-page .stats,.txn-list-page .filters,.txn-list-page .date-range-inputs{grid-template-columns:1fr}.txn-list-page .date-range-field{grid-column:span 1}.txn-list-page .quick-actions{display:grid}.txn-list-page .page-title{display:grid}.txn-drawer{width:100%}}@media print{.sidebar,.topbar,.txn-list-page .filters,.txn-list-page .quick-actions,.txn-list-page .action-cell,.txn-drawer-actions,.txn-toast{display:none!important}.txn-list-page .content,.content{padding:0}.txn-list-page .card{box-shadow:none}.txn-list-page table{min-width:0}.txn-list-page .table-wrap{overflow:visible}}
</style>
@endpush

@section('content')
@php
    $money = fn ($amount) => ($currency ?? 'BDT') . ' ' . number_format((float) $amount, 2);
    $formatDate = function ($date) {
        if (! $date) {
            return '—';
        }

        try {
            return \Illuminate\Support\Carbon::parse($date)->format('d M Y');
        } catch (\Throwable $exception) {
            return (string) $date;
        }
    };
    $natureBadge = function ($nature) {
        return match ($nature) {
            'Receipt' => 'badge-receipt',
            'Payment' => 'badge-payment',
            'Due' => 'badge-due',
            'Advance' => 'badge-advance',
            default => 'badge-adjustment',
        };
    };
    $statusBadge = function ($status) {
        $statusText = strtolower((string) $status);
        if (str_contains($statusText, 'posted')) {
            return 'badge-posted';
        }
        if (str_contains($statusText, 'draft')) {
            return 'badge-draft';
        }
        if (str_contains($statusText, 'pending')) {
            return 'badge-pending';
        }
        if (str_contains($statusText, 'reverse') || str_contains($statusText, 'cancel')) {
            return 'badge-reversed';
        }
        return 'badge-draft';
    };
    $currentPage = $transactions->currentPage();
    $lastPage = $transactions->lastPage();
    $startPage = max(1, $currentPage - 2);
    $endPage = min($lastPage, $currentPage + 2);
    $selectedGroupId = (string) ($filters['account_group_id'] ?? '');
    $selectedAccountId = (string) ($filters['account_id'] ?? '');
@endphp

<div class="txn-list-page">
    <div class="page-title">
        <div>
            <h2>Transaction List</h2>
            <p>View, search, filter, print, export, and manage posted transactions.</p>
        </div>
        <div class="quick-actions">
            <a class="btn-outline" id="exportBtn" href="{{ route('accounting-reports.transactions.export', array_merge(request()->query(), ['format' => 'xlsx'])) }}">⇩ Export XLSX</a>
            <a class="btn-outline" href="{{ route('accounting-reports.transactions.export', array_merge(request()->query(), ['format' => 'pdf'])) }}">⇩ Export PDF</a>
            <button class="btn-ghost" id="printBtn" type="button">Print</button>
            @if(Route::has('transactions.create'))
                <a class="btn-primary" id="addBtn" href="{{ route('transactions.create') }}">+ Add Transaction</a>
            @endif
        </div>
    </div>

    @if(session('success'))
        <div class="card" style="padding:14px 18px;margin-bottom:18px;border-color:#bbf7d0;background:#f0fdf4;color:#166534;font-weight:750">
            {{ session('success') }}
        </div>
    @endif

    @if($errors->any())
        <div class="card" style="padding:14px 18px;margin-bottom:18px;border-color:#fecaca;background:#fef2f2;color:#991b1b;font-weight:750">
            {{ $errors->first() }}
        </div>
    @endif

    <div class="stats">
        <div class="card stat"><span>Total Transactions</span><strong id="statCount">{{ number_format((int) ($stats->total_transactions ?? 0)) }}</strong><small>Based on current filter</small></div>
        <div class="card stat"><span>Total Receipt</span><strong class="green" id="statReceipt">{{ $money($stats->total_receipt ?? 0) }}</strong><small>Cash and bank in</small></div>
        <div class="card stat"><span>Total Payment</span><strong class="red" id="statPayment">{{ $money($stats->total_payment ?? 0) }}</strong><small>Cash and bank out</small></div>
        <div class="card stat"><span>Draft / Pending</span><strong class="orange" id="statDraft">{{ number_format((int) ($stats->total_draft ?? 0)) }}</strong><small>Need review</small></div>
    </div>

    <form method="GET" action="{{ route('accounting-reports.transactions.index') }}" class="card filters accounting-filter-sequence" id="transactionFilterForm">
        <div class="field date-range-field"><label>Date Range</label><div class="date-range-inputs"><input id="fromDate" name="from_date" type="date" value="{{ $filters['from_date'] ?? '' }}" aria-label="From Date"><input id="toDate" name="to_date" type="date" value="{{ $filters['to_date'] ?? '' }}" aria-label="To Date"></div></div>
        <div><label>Account Group</label><select id="transactionAccountGroupFilter" name="account_group_id"><option value="">All Account Groups</option>@foreach($accountGroups as $group)<option value="{{ $group->id }}" data-account-type-id="{{ $group->account_type_id }}" @selected($selectedGroupId === (string) $group->id)>{{ $group->account_code }} - {{ $group->account_name }}</option>@endforeach</select></div>
        <div><label>Ledger Account</label><select id="transactionLedgerAccountFilter" name="account_id"><option value="">All Ledger Accounts</option>@foreach($ledgerAccounts as $ledgerAccount)<option value="{{ $ledgerAccount->id }}" data-parent-id="{{ $ledgerAccount->parent_id }}" data-account-type-id="{{ $ledgerAccount->account_type_id }}" @selected($selectedAccountId === (string) $ledgerAccount->id)>{{ $ledgerAccount->account_code }} - {{ $ledgerAccount->account_name }}</option>@endforeach</select></div>
        <div><label>Party</label><select id="partyFilter" name="party_id"><option value="">All Parties</option>@foreach($parties as $party)<option value="{{ $party->id }}" @selected((string) ($filters['party_id'] ?? '') === (string) $party->id)>{{ $party->party_code }} - {{ $party->party_name }}</option>@endforeach</select></div>
        <div><label>Voucher Type</label><select id="voucherTypeFilter" name="voucher_type"><option value="All">All Voucher Types</option>@foreach($voucherTypes as $voucherType)<option value="{{ $voucherType }}" @selected(($filters['voucher_type'] ?? 'All') === $voucherType)>{{ $voucherType }}</option>@endforeach</select></div>
        <div><label>Transaction Head</label><select id="transactionHeadFilter" name="transaction_head_id"><option value="">All Transaction Heads</option>@foreach($transactionHeads as $head)<option value="{{ $head->id }}" @selected((string) ($filters['transaction_head_id'] ?? '') === (string) $head->id)>{{ $head->head_code }} - {{ $head->name }}</option>@endforeach</select></div>
        <div><label>Status</label><select id="statusFilter" name="status">@foreach(['All', 'Posted', 'Draft', 'Pending Review', 'Reversed', 'Cancelled'] as $status)<option value="{{ $status }}" @selected(($filters['status'] ?? 'All') === $status)>{{ $status }}</option>@endforeach</select></div>
        <div><label>Nature</label><select id="natureFilter" name="nature">@foreach(['All', 'Payment', 'Receipt', 'Due', 'Advance', 'Adjustment'] as $nature)<option value="{{ $nature }}" @selected(($filters['nature'] ?? 'All') === $nature)>{{ $nature }}</option>@endforeach</select></div>
        <div class="field search-field"><label>Search</label><span>⌕</span><input id="searchInput" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="Voucher, head, party, reference..."></div>
        <button class="btn-outline" id="resetBtn" type="button">Reset</button>
    </form>

    <div class="card table-card">
        <div class="table-head"><div><h3>Transactions</h3><span id="resultText">Showing transaction records using the standard accounting filter sequence</span></div><button class="btn-ghost" id="bulkBtn" type="button">Bulk Action</button></div>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th><input type="checkbox" id="selectAll"></th>
                        <th>Date</th>
                        <th>Voucher No.</th>
                        <th>Transaction Head</th>
                        <th>Party / Person</th>
                        <th>Settlement</th>
                        <th>Nature</th>
                        <th>Amount</th>
                        <th>Status</th>
                        <th>Reference</th>
                        <th style="text-align:right">Actions</th>
                    </tr>
                </thead>
                <tbody id="txnTable">
                    @forelse($transactions as $transaction)
                        @php
                            $ledgerLines = collect($transaction->journal_lines ?? [])->map(fn ($line) => [
                                'account' => trim(($line->account_code ? $line->account_code . ' - ' : '') . $line->account_name),
                                'debit' => $money($line->debit ?? 0),
                                'credit' => $money($line->credit ?? 0),
                                'description' => $line->description ?? '',
                            ])->values();
                            $statusText = (string) ($transaction->status ?: '—');
                            $isAlreadyReversed = str_contains(strtolower($statusText), 'reverse') || str_contains(strtolower($statusText), 'cancel');
                        @endphp
                        <tr data-date="{{ $transaction->voucher_date }}" data-nature="{{ $transaction->nature }}" data-status="{{ $statusText }}" data-amount="{{ (float) $transaction->amount }}">
                            <td><input type="checkbox" class="row-check"></td>
                            <td>{{ $formatDate($transaction->voucher_date) }}</td>
                            <td class="voucher">{{ $transaction->voucher_no }}</td>
                            <td class="head-name">{{ $transaction->purpose_name ?: $transaction->purpose_code ?: $transaction->voucher_type_code ?: '—' }}</td>
                            <td>{{ $transaction->party_name ?: '—' }}</td>
                            <td>{{ $transaction->settlement ?: '—' }}</td>
                            <td><span class="badge {{ $natureBadge($transaction->nature) }}">{{ $transaction->nature }}</span></td>
                            <td class="amount">{{ $money($transaction->amount) }}</td>
                            <td><span class="badge {{ $statusBadge($statusText) }}">{{ $statusText }}</span></td>
                            <td>{{ $transaction->reference_no ?: '—' }}</td>
                            <td>
                                <div class="action-cell">
                                    <button
                                        class="icon-btn view-btn"
                                        type="button"
                                        title="View"
                                        data-voucher-id="{{ $transaction->voucher_id }}"
                                        data-date="{{ $formatDate($transaction->voucher_date) }}"
                                        data-voucher="{{ $transaction->voucher_no }}"
                                        data-head="{{ $transaction->purpose_name ?: $transaction->purpose_code ?: $transaction->voucher_type_code ?: '—' }}"
                                        data-party="{{ $transaction->party_name ?: '—' }}"
                                        data-settlement="{{ $transaction->settlement ?: '—' }}"
                                        data-amount="{{ $money($transaction->amount) }}"
                                        data-ref="{{ $transaction->reference_no ?: '—' }}"
                                        data-status="{{ $statusText }}"
                                        data-ledger='@json($ledgerLines)'
                                        data-reverse-form="reverse-form-{{ $transaction->voucher_id }}"
                                    >👁</button>
                                    <button class="icon-btn edit-btn" type="button" title="Edit" data-status="{{ $statusText }}" data-voucher="{{ $transaction->voucher_no }}">✎</button>
                                    <button class="icon-btn reverse-btn is-danger" type="button" title="Reverse" data-status="{{ $statusText }}" data-voucher="{{ $transaction->voucher_no }}" data-form="reverse-form-{{ $transaction->voucher_id }}">↩</button>
                                    @if(! $isAlreadyReversed)
                                        <form id="reverse-form-{{ $transaction->voucher_id }}" method="POST" action="{{ route('accounting-reports.transactions.reverse', $transaction->voucher_id) }}" style="display:none">
                                            @csrf
                                        </form>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr data-empty="true"><td colspan="11" class="empty-state">No transaction found for the selected filter.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="table-footer">
            <span id="footerText">Showing {{ $transactions->firstItem() ?? 0 }} to {{ $transactions->lastItem() ?? 0 }} of {{ $transactions->total() }} entries</span>
            <div class="pagination">
                <a class="page-btn {{ $transactions->onFirstPage() ? 'disabled' : '' }}" href="{{ $transactions->previousPageUrl() ?: '#' }}">‹</a>
                @for($page = $startPage; $page <= $endPage; $page++)
                    <a class="page-btn {{ $page === $currentPage ? 'active' : '' }}" href="{{ $transactions->url($page) }}">{{ $page }}</a>
                @endfor
                <a class="page-btn {{ $transactions->hasMorePages() ? '' : 'disabled' }}" href="{{ $transactions->nextPageUrl() ?: '#' }}">›</a>
            </div>
        </div>
    </div>
</div>

<div class="txn-drawer" id="txnDrawer" aria-hidden="true">
    <div class="txn-drawer-head"><div><h3 id="drawerTitle">Transaction Details</h3><p id="drawerSub">Voucher details and generated ledger entry</p></div><button class="txn-close" id="closeDrawer" type="button">×</button></div>
    <div class="txn-drawer-body">
        <div class="txn-detail-card"><h4>Basic Information</h4><div class="txn-detail-row"><span>Date</span><strong id="dDate">-</strong></div><div class="txn-detail-row"><span>Voucher</span><strong id="dVoucher">-</strong></div><div class="txn-detail-row"><span>Head</span><strong id="dHead">-</strong></div><div class="txn-detail-row"><span>Party</span><strong id="dParty">-</strong></div><div class="txn-detail-row"><span>Settlement</span><strong id="dSettlement">-</strong></div><div class="txn-detail-row"><span>Amount</span><strong id="dAmount">-</strong></div><div class="txn-detail-row"><span>Reference</span><strong id="dRef">-</strong></div></div>
        <div class="txn-detail-card"><h4>Generated Ledger Entry</h4><table class="txn-ledger-table"><thead><tr><th>Account</th><th>Debit</th><th>Credit</th></tr></thead><tbody id="drawerLedger"></tbody></table></div>
    </div>
    <div class="txn-drawer-actions"><button class="btn-outline" id="reverseBtn" type="button">Reverse</button><button class="btn-primary" id="printVoucherBtn" type="button">Print Voucher</button></div>
</div>
<div class="txn-toast" id="txnToast">Action completed.</div>
@endsection

@push('scripts')
<script>
(function () {
    const filterForm = document.getElementById('transactionFilterForm');
    const drawer = document.getElementById('txnDrawer');
    const toast = document.getElementById('txnToast');
    const showToast = (message) => {
        if (window.AccountingUI?.showToast) {
            window.AccountingUI.showToast(message);
            return;
        }
        toast.textContent = message;
        toast.classList.add('show');
        setTimeout(() => toast.classList.remove('show'), 2300);
    };
    const setText = (id, value) => {
        const element = document.getElementById(id);
        if (element) element.textContent = value || '—';
    };
    const isLockedStatus = (status) => {
        const text = String(status || '').toLowerCase();
        return text.includes('posted') || text.includes('reverse') || text.includes('cancel');
    };
    const submitFilter = () => {
        if (filterForm) filterForm.submit();
    };
    let searchTimer;
    const accountGroupFilter = document.getElementById('transactionAccountGroupFilter');
    const ledgerAccountFilter = document.getElementById('transactionLedgerAccountFilter');
    const syncLedgerAccountOptions = () => {
        if (!accountGroupFilter || !ledgerAccountFilter) return;
        const selectedGroup = accountGroupFilter.selectedOptions[0];
        const groupId = accountGroupFilter.value;
        const accountTypeId = selectedGroup?.dataset.accountTypeId || '';
        let hasVisibleSelected = !ledgerAccountFilter.value;
        [...ledgerAccountFilter.options].forEach((option) => {
            if (!option.value) {
                option.hidden = false;
                option.disabled = false;
                return;
            }
            const parentId = option.dataset.parentId || '';
            const optionAccountType = option.dataset.accountTypeId || '';
            const visible = !groupId || parentId === groupId || (!parentId && optionAccountType === accountTypeId);
            option.hidden = !visible;
            option.disabled = !visible;
            if (visible && option.selected) hasVisibleSelected = true;
        });
        if (!hasVisibleSelected) {
            ledgerAccountFilter.value = '';
        }
    };
    syncLedgerAccountOptions();
    accountGroupFilter?.addEventListener('change', syncLedgerAccountOptions);

    document.getElementById('searchInput')?.addEventListener('input', () => {
        clearTimeout(searchTimer);
        searchTimer = setTimeout(submitFilter, 650);
    });
    ['fromDate', 'toDate', 'transactionAccountGroupFilter', 'transactionLedgerAccountFilter', 'partyFilter', 'voucherTypeFilter', 'transactionHeadFilter', 'statusFilter', 'natureFilter'].forEach((id) => {
        document.getElementById(id)?.addEventListener('change', submitFilter);
    });
    document.getElementById('resetBtn')?.addEventListener('click', () => {
        window.location.href = @json(route('accounting-reports.transactions.index'));
    });
    document.getElementById('printBtn')?.addEventListener('click', () => window.print());
    document.getElementById('selectAll')?.addEventListener('change', (event) => {
        document.querySelectorAll('.row-check').forEach((checkbox) => {
            const row = checkbox.closest('tr');
            if (!row || row.dataset.empty === 'true') return;
            checkbox.checked = event.target.checked;
            row.classList.toggle('selected', checkbox.checked);
        });
    });
    document.querySelectorAll('.row-check').forEach((checkbox) => {
        checkbox.addEventListener('change', () => checkbox.closest('tr')?.classList.toggle('selected', checkbox.checked));
    });
    document.getElementById('bulkBtn')?.addEventListener('click', () => {
        const count = document.querySelectorAll('.row-check:checked').length;
        showToast(count ? count + ' transaction(s) selected for bulk action.' : 'Please select at least one transaction.');
    });
    document.querySelectorAll('.view-btn').forEach((button) => {
        button.addEventListener('click', () => {
            const ledger = JSON.parse(button.dataset.ledger || '[]');
            setText('dDate', button.dataset.date);
            setText('dVoucher', button.dataset.voucher);
            setText('dHead', button.dataset.head);
            setText('dParty', button.dataset.party);
            setText('dSettlement', button.dataset.settlement);
            setText('dAmount', button.dataset.amount);
            setText('dRef', button.dataset.ref);
            setText('drawerTitle', button.dataset.voucher);
            const drawerLedger = document.getElementById('drawerLedger');
            drawerLedger.innerHTML = ledger.length
                ? ledger.map((line) => `<tr><td>${line.account || '—'}</td><td>${line.debit || 'BDT 0.00'}</td><td>${line.credit || 'BDT 0.00'}</td></tr>`).join('')
                : '<tr><td colspan="3" style="text-align:center;color:#667085">No ledger lines found.</td></tr>';
            document.getElementById('reverseBtn').dataset.form = button.dataset.reverseForm || '';
            document.getElementById('reverseBtn').dataset.status = button.dataset.status || '';
            document.getElementById('reverseBtn').dataset.voucher = button.dataset.voucher || '';
            drawer.classList.add('open');
            drawer.setAttribute('aria-hidden', 'false');
        });
    });
    document.querySelectorAll('.edit-btn').forEach((button) => {
        button.addEventListener('click', () => {
            if (isLockedStatus(button.dataset.status)) {
                showToast('Posted transactions cannot be edited directly. Reverse it and create a corrected transaction.');
                return;
            }
            showToast('Draft edit action is reserved for the transaction entry screen.');
        });
    });
    const reverseByButton = (button) => {
        if (!button) return;
        if (isLockedStatus(button.dataset.status) && String(button.dataset.status || '').toLowerCase().includes('reverse')) {
            showToast('This transaction is already reversed.');
            return;
        }
        const formId = button.dataset.form;
        const form = formId ? document.getElementById(formId) : null;
        if (!form) {
            showToast('Reverse action is not available for this transaction.');
            return;
        }
        if (confirm('Reverse ' + (button.dataset.voucher || 'this transaction') + '?')) {
            form.submit();
        }
    };
    document.querySelectorAll('.reverse-btn').forEach((button) => button.addEventListener('click', () => reverseByButton(button)));
    document.getElementById('reverseBtn')?.addEventListener('click', (event) => reverseByButton(event.currentTarget));
    document.getElementById('closeDrawer')?.addEventListener('click', () => {
        drawer.classList.remove('open');
        drawer.setAttribute('aria-hidden', 'true');
    });
    document.getElementById('printVoucherBtn')?.addEventListener('click', () => window.print());
})();
</script>
@endpush
