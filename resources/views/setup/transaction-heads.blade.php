@extends('layouts.app')

@section('title', 'Transaction Head Setup | HisebGhor')

@push('styles')
<style>
    .transaction-head-page .tx-head-layout{display:grid;grid-template-columns:minmax(0,1fr);gap:18px}
    .transaction-head-page .tx-head-form-note{border:1px solid #dbeafe;background:#eff6ff;border-radius:14px;padding:14px;color:#1e3a8a;line-height:1.55}
    .transaction-head-page .tx-head-form-note strong{display:block;margin-bottom:3px}
    .transaction-head-page .tx-head-filter-grid{display:grid;grid-template-columns:minmax(240px,2fr) repeat(3,minmax(150px,1fr));gap:12px;align-items:end;margin-bottom:16px}
    .transaction-head-page .tx-head-filter-grid label{display:block;margin:0 0 6px;color:#344054;font-size:12px;font-weight:850}
    .transaction-head-page .tx-head-filter-grid input,.transaction-head-page .tx-head-filter-grid select{width:100%;min-height:46px;border-radius:14px}
    .transaction-head-page .tx-head-table-wrap{max-height:620px;overflow:auto;scrollbar-gutter:stable both-edges}
    .transaction-head-page .tx-head-table-wrap thead th{position:sticky;top:0;z-index:2}
    .transaction-head-page .tx-head-code-input{background:#f8fafc;color:#475467;font-weight:800}
    .transaction-head-page .rule-summary{display:flex;gap:6px;flex-wrap:wrap;align-items:center}
    .transaction-head-page .rule-summary small{display:block;width:100%;color:var(--muted);margin-top:2px}
    .transaction-head-page .setup-warning{color:#b54708;font-size:12px;font-weight:750;margin-top:4px}
    @media(max-width:980px){.transaction-head-page .tx-head-filter-grid{grid-template-columns:1fr 1fr}}
    @media(max-width:640px){.transaction-head-page .tx-head-filter-grid{grid-template-columns:1fr}}
</style>
@endpush

@section('content')
@php
    $readyHeads = $transactionHeads->filter(fn ($head) => (bool) data_get($transactionHeadProfiles, $head->id . '.ready'))->count();
    $incompleteHeads = $transactionHeads->filter(fn ($head) => data_get($transactionHeadProfiles, $head->id . '.setup_status') === 'Accounting Rule Required')->count();
    $activeHeads = $transactionHeads->where('status', 'Active')->count();
    $categoryFilterOptions = collect(\App\Models\TransactionHead::transactionCategories())
        ->merge($transactionHeads->pluck('category')->filter())
        ->unique()
        ->values();
@endphp

<div class="prototype-page transaction-head-page">
    <div class="prototype-hero">
        <div>
            <span class="page-label">Transaction Head Setup</span>
            <h2>Transaction Heads</h2>
            <p>Create simple business activities. Accounting Rules decide Party, Money Account, settlement, Debit and Credit automatically.</p>
        </div>
        <div class="prototype-actions">
            <button class="btn-outline" type="button" id="addHeadBtn">+ Add Transaction Head</button>
            <a class="btn-ghost" href="{{ route('setup.accounting-rules-setup') }}">Configure Accounting Rules</a>
        </div>
    </div>

    @include('partials.setup-progress', ['current' => 5])

    <div class="prototype-stats four">
        <div class="card prototype-stat"><span>Total Heads</span><strong>{{ $transactionHeads->count() }}</strong><small>Business activities</small></div>
        <div class="card prototype-stat"><span>Ready for Entry</span><strong>{{ $readyHeads }}</strong><small>Has a valid active rule</small></div>
        <div class="card prototype-stat"><span>Needs Rule</span><strong>{{ $incompleteHeads }}</strong><small>Not shown in Transaction Entry</small></div>
        <div class="card prototype-stat"><span>Active</span><strong>{{ $activeHeads }}</strong><small>Enabled setup records</small></div>
    </div>

    <div class="tx-head-layout">
        <section class="card prototype-card" id="headFormCard">
            <div class="prototype-card-header">
                <div>
                    <h3 id="headFormTitle">Add Transaction Head</h3>
                    <p>Only the business activity and its main Posting COA belong here.</p>
                </div>
                <span class="badge badge-primary">User-friendly setup</span>
            </div>

            <div class="prototype-card-body">
                <div class="tx-head-form-note" style="margin-bottom:16px">
                    <strong>Accounting behavior is configured separately.</strong>
                    Party requirement, Money Account requirement, settlement type, reference requirement and Debit/Credit lines come from Accounting Rules—not from this form.
                </div>

                <form
                    id="headForm"
                    class="prototype-form"
                    data-frontend-form
                    data-action="{{ route('api.transaction-heads.store') }}"
                    data-store-url="{{ route('api.transaction-heads.store') }}"
                    data-success="Transaction Head saved successfully."
                >
                    @csrf
                    <input type="hidden" name="_method" id="headFormMethod" value="POST">

                    <div class="prototype-form-grid two">
                        <div class="prototype-field">
                            <label for="headCodeDisplay">Transaction Head ID</label>
                            <input id="headCodeDisplay" class="tx-head-code-input" value="Generated automatically after save" readonly tabindex="-1">
                        </div>

                        <div class="prototype-field">
                            <label for="headName">Transaction Head Name <span class="required">*</span></label>
                            <input id="headName" name="name" placeholder="Example: Fuel Expense" required maxlength="255">
                            
                        </div>

                        <div class="prototype-field">
                            <label for="headCategory">Category <span class="required">*</span></label>
                            <select id="headCategory" name="category" required>
                                <option value="">Select category</option>
                                @foreach(\App\Models\TransactionHead::transactionCategories() as $categoryOption)
                                    <option value="{{ $categoryOption }}">{{ $categoryOption }}</option>
                                @endforeach
                            </select>
                            
                        </div>

                        <div class="prototype-field">
                            <label for="postingLedger">Posting COA <span class="required">*</span></label>
                            <select id="postingLedger" name="default_primary_ledger_id" required>
                                <option value="">Select posting ledger</option>
                                @foreach($postingLedgers as $ledger)
                                    <option value="{{ $ledger->id }}">{{ $ledger->display_name }}</option>
                                @endforeach
                            </select>
                            
                        </div>

                        <div class="prototype-field full">
                            <label for="helpText">User Guidance</label>
                            <textarea id="helpText" name="help_text" maxlength="1000" placeholder="Example: Use this head to record vehicle fuel purchases."></textarea>
                            
                        </div>

                        <div class="prototype-field">
                            <label for="headStatus">Status <span class="required">*</span></label>
                            <select id="headStatus" name="status" required>
                                <option value="Active">Active</option>
                                <option value="Inactive">Inactive</option>
                            </select>
                        </div>
                    </div>

                    <div class="prototype-form-actions">
                        <button type="button" class="btn-ghost" id="cancelHeadBtn">Clear</button>
                        <button type="submit" class="btn-primary" id="saveHeadBtn">Save Transaction Head</button>
                    </div>
                </form>
            </div>
        </section>

        <section class="card prototype-card prototype-section" id="headListCard">
            <div class="prototype-card-header">
                <div>
                    <h3>Transaction Head List</h3>
                    <p>Setup status is calculated from active Accounting Rules.</p>
                </div>
                <span class="badge badge-neutral" id="resultCount">{{ $transactionHeads->count() }} entries</span>
            </div>

            <div class="prototype-card-body">
                <div class="tx-head-filter-grid" aria-label="Transaction Head filters">
                    <div>
                        <label for="headFilterSearch">Search</label>
                        <input id="headFilterSearch" placeholder="Search ID, name or COA...">
                    </div>
                    <div>
                        <label for="headFilterCategory">Category</label>
                        <select id="headFilterCategory">
                            <option value="">All Categories</option>
                            @foreach($categoryFilterOptions as $categoryOption)
                                <option value="{{ $categoryOption }}">{{ $categoryOption }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label for="headFilterSetup">Setup Status</label>
                        <select id="headFilterSetup">
                            <option value="">All Setup Statuses</option>
                            <option value="Ready">Ready</option>
                            <option value="Accounting Rule Required">Accounting Rule Required</option>
                            <option value="Inactive">Inactive</option>
                        </select>
                    </div>
                    <div>
                        <label for="headFilterStatus">Record Status</label>
                        <select id="headFilterStatus">
                            <option value="">All Statuses</option>
                            <option value="Active">Active</option>
                            <option value="Inactive">Inactive</option>
                        </select>
                    </div>
                </div>

                <div class="table-wrap prototype-table-wrap always-scroll tx-head-table-wrap" id="headTableWrap">
                    <table id="headTable" data-no-client-pagination="true">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Transaction Head</th>
                                <th>Category</th>
                                <th>Posting COA</th>
                                <th>Accounting Rules</th>
                                <th>Setup Status</th>
                                <th>Status</th>
                                <th style="text-align:right">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($transactionHeads as $head)
                                @php
                                    $profile = $transactionHeadProfiles[$head->id] ?? [];
                                    $setupStatus = $profile['setup_status'] ?? 'Accounting Rule Required';
                                    $settlementNames = collect($profile['settlement_names'] ?? []);
                                @endphp
                                <tr
                                    data-head-row="true"
                                    data-text="{{ e(trim(($head->head_code ?: '') . ' ' . $head->name . ' ' . $head->category . ' ' . ($head->defaultPrimaryLedger?->display_name ?: ''))) }}"
                                    data-head-code="{{ e($head->head_code) }}"
                                    data-name="{{ e($head->name) }}"
                                    data-category="{{ e($head->category) }}"
                                    data-posting-ledger-id="{{ $head->default_primary_ledger_id }}"
                                    data-help-text="{{ e($head->help_text) }}"
                                    data-status="{{ e($head->status) }}"
                                    data-setup-status="{{ e($setupStatus) }}"
                                    data-update-url="{{ route('api.transaction-heads.update', $head) }}"
                                >
                                    <td class="code">{{ $head->head_code ?: '—' }}</td>
                                    <td>
                                        <strong>{{ $head->name }}</strong>
                                        @if($head->help_text)
                                            <div class="hint" style="margin-top:4px">{{ $head->help_text }}</div>
                                        @endif
                                    </td>
                                    <td><span class="badge badge-primary">{{ $head->category }}</span></td>
                                    <td class="{{ $head->defaultPrimaryLedger ? '' : 'muted' }}">
                                        {{ $head->defaultPrimaryLedger?->display_name ?? 'Not assigned' }}
                                    </td>
                                    <td>
                                        <div class="rule-summary">
                                            <span class="badge badge-neutral">{{ (int) ($profile['active_rule_count'] ?? 0) }} active</span>
                                            @if($settlementNames->isNotEmpty())
                                                @foreach($settlementNames->take(3) as $settlementName)
                                                    <span class="badge badge-primary">{{ $settlementName }}</span>
                                                @endforeach
                                            @else
                                                <small>No rule settlement configured.</small>
                                            @endif
                                        </div>
                                    </td>
                                    <td>
                                        <span class="badge {{ $setupStatus === 'Ready' ? 'badge-success' : ($setupStatus === 'Inactive' ? 'badge-neutral' : 'badge-warning') }}">
                                            {{ $setupStatus }}
                                        </span>
                                        @if($setupStatus === 'Accounting Rule Required')
                                            <div class="setup-warning">Configure a balanced active rule before transaction use.</div>
                                        @endif
                                    </td>
                                    <td><span class="badge {{ $head->status === 'Active' ? 'badge-success' : 'badge-neutral' }}">{{ $head->status }}</span></td>
                                    <td>
                                        <div class="action-cell">
                                            <button class="icon-btn edit-btn" type="button" title="Edit">✎</button>
                                            <a class="icon-btn" href="{{ route('setup.accounting-rules-setup') }}" title="Configure Accounting Rules">⚙</a>
                                            <form method="POST" data-delete-form action="{{ route('setup.transaction-heads.destroy', $head) }}" onsubmit="return confirm('Delete this Transaction Head? Used or configured Heads cannot be deleted and should be deactivated instead.')">
                                                @csrf
                                                @method('DELETE')
                                                <button class="icon-btn delete-btn" type="submit" title="Delete">🗑</button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr data-empty="true"><td colspan="8" class="muted" style="text-align:center;padding:24px">No Transaction Heads found.</td></tr>
                            @endforelse
                            <tr id="headFilterEmptyRow" style="display:none">
                                <td colspan="8" class="muted" style="text-align:center;padding:24px">No Transaction Heads match the current filters.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </section>
    </div>
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', () => {
    const form = document.getElementById('headForm');
    if (!form) return;

    const methodInput = document.getElementById('headFormMethod');
    const codeDisplay = document.getElementById('headCodeDisplay');
    const title = document.getElementById('headFormTitle');
    const saveButton = document.getElementById('saveHeadBtn');
    const fields = {
        name: form.querySelector('[name="name"]'),
        category: form.querySelector('[name="category"]'),
        postingLedger: form.querySelector('[name="default_primary_ledger_id"]'),
        helpText: form.querySelector('[name="help_text"]'),
        status: form.querySelector('[name="status"]'),
    };

    function resetForm() {
        form.reset();
        form.dataset.action = form.dataset.storeUrl;
        methodInput.value = 'POST';
        codeDisplay.value = 'Generated automatically after save';
        fields.status.value = 'Active';
        title.textContent = 'Add Transaction Head';
        saveButton.textContent = 'Save Transaction Head';
        fields.name.focus();
    }

    function loadForEdit(row) {
        form.dataset.action = row.dataset.updateUrl;
        methodInput.value = 'PUT';
        codeDisplay.value = row.dataset.headCode || '—';
        fields.name.value = row.dataset.name || '';
        fields.category.value = row.dataset.category || '';
        fields.postingLedger.value = row.dataset.postingLedgerId || '';
        fields.helpText.value = row.dataset.helpText || '';
        fields.status.value = row.dataset.status || 'Active';
        title.textContent = `Edit ${row.dataset.headCode || 'Transaction Head'}`;
        saveButton.textContent = 'Update Transaction Head';
        document.getElementById('headFormCard').scrollIntoView({ behavior: 'smooth', block: 'start' });
    }

    document.querySelectorAll('#headTable .edit-btn').forEach((button) => {
        button.addEventListener('click', () => loadForEdit(button.closest('tr')));
    });

    document.getElementById('addHeadBtn')?.addEventListener('click', resetForm);
    document.getElementById('cancelHeadBtn')?.addEventListener('click', resetForm);

    const search = document.getElementById('headFilterSearch');
    const category = document.getElementById('headFilterCategory');
    const setup = document.getElementById('headFilterSetup');
    const status = document.getElementById('headFilterStatus');
    const emptyRow = document.getElementById('headFilterEmptyRow');
    const resultCount = document.getElementById('resultCount');

    function applyFilters() {
        const searchValue = String(search?.value || '').toLowerCase().trim();
        const categoryValue = String(category?.value || '').toLowerCase();
        const setupValue = String(setup?.value || '').toLowerCase();
        const statusValue = String(status?.value || '').toLowerCase();
        let shown = 0;

        document.querySelectorAll('#headTable tbody tr[data-head-row="true"]').forEach((row) => {
            const matches = (!searchValue || String(row.dataset.text || '').toLowerCase().includes(searchValue))
                && (!categoryValue || String(row.dataset.category || '').toLowerCase() === categoryValue)
                && (!setupValue || String(row.dataset.setupStatus || '').toLowerCase() === setupValue)
                && (!statusValue || String(row.dataset.status || '').toLowerCase() === statusValue);

            row.style.display = matches ? '' : 'none';
            if (matches) shown++;
        });

        if (emptyRow) emptyRow.style.display = shown === 0 ? '' : 'none';
        if (resultCount) resultCount.textContent = `${shown} ${shown === 1 ? 'entry' : 'entries'}`;
    }

    [search, category, setup, status].forEach((control) => {
        control?.addEventListener('input', applyFilters);
        control?.addEventListener('change', applyFilters);
    });

    applyFilters();
});
</script>
@endpush
