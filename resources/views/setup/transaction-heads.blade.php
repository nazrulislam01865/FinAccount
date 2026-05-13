@extends('layouts.app')

@section('title', 'Transaction Head Setup | Accounting System')

@section('content')
<div class="page-title">
    <div>
        <span class="page-label">Transaction Head Setup</span>
        <h2>Transaction Head Setup</h2>
        <p>Define user-friendly transaction types for daily entries.</p>
    </div>
</div>

@include('partials.setup-progress', ['current' => 5])

<div class="layout">
    <div class="left-stack">
        <div class="card toolbar five" data-table-filter="#headTable" data-count-target="#resultCount">
            <div class="field search-field">
                <span>⌕</span>
                <input placeholder="Search heads..." data-filter-key="text">
            </div>

            <div>
                <label>Nature</label>
                <select
                    data-filter-key="nature"
                    data-dropdown="/api/dropdowns/transaction-head-natures"
                    data-placeholder="All Categories"
                ></select>
            </div>

            <div>
                <label>Party Type</label>
                <select
                    data-filter-key="party"
                    data-dropdown="/api/dropdowns/party-types"
                    data-placeholder="All Party Types"
                ></select>
            </div>

            <div>
                <label>Status</label>
                <select data-filter-key="status">
                    <option value="">All Status</option>
                    <option value="Active">Active</option>
                    <option value="Inactive">Inactive</option>
                </select>
            </div>

            <button class="btn-primary" type="button" id="addHeadBtn">
                + Add Transaction Head
            </button>
        </div>

        <div class="card table-card">
            <table id="headTable">
                <thead>
                    <tr>
                        <th>Head Name</th>
                        <th>Nature</th>
                        <th>Default Party Type</th>
                        <th>Requires Party</th>
                        <th>Requires Reference</th>
                        <th>Allowed Settlement Types</th>
                        <th>Status</th>
                        <th style="text-align:right">Actions</th>
                    </tr>
                </thead>

                <tbody>
                    @forelse($transactionHeads as $head)
                        <tr
                            data-id="{{ $head->id }}"
                            data-name="{{ e($head->name) }}"
                            data-nature="{{ $head->nature }}"
                            data-party="{{ $head->default_party_type_id }}"
                            data-requires-party="{{ $head->requires_party ? 1 : 0 }}"
                            data-requires-reference="{{ $head->requires_reference ? 1 : 0 }}"
                            data-description="{{ e($head->description) }}"
                            data-settlements='{{ $head->settlementTypes->pluck('name')->values()->toJson() }}'
                            data-status="{{ $head->status }}"
                            data-update-url="{{ url('/api/transaction-heads/' . $head->id) }}"
                        >
                            <td class="strong">{{ $head->name }}</td>

                            <td>
                                <span class="badge {{ $head->nature === 'Receipt' ? 'badge-success' : 'badge-neutral' }}">
                                    {{ $head->nature }}
                                </span>
                            </td>

                            <td class="{{ $head->defaultPartyType ? '' : 'muted' }}">
                                {{ $head->defaultPartyType?->name ?? '—' }}
                            </td>

                            <td>{{ $head->requires_party ? 'Yes' : 'No' }}</td>

                            <td>{{ $head->requires_reference ? 'Yes' : 'No' }}</td>

                            <td>
                                {{ $head->settlementTypes->pluck('name')->join(', ') ?: '—' }}
                            </td>

                            <td>
                                <span class="badge {{ $head->status === 'Active' ? 'badge-success' : 'badge-neutral' }}">
                                    {{ $head->status }}
                                </span>
                            </td>

                            <td>
                                <div class="action-cell">
                                    <button class="icon-btn edit-btn" type="button" title="Edit">✎</button>

                                    <form
                                        method="POST"
                                        data-delete-form
                                        action="{{ url('/setup/transaction-heads/' . $head->id) }}"
                                        onsubmit="return confirm('Delete this transaction head?')"
                                    >
                                        @csrf
                                        @method('DELETE')

                                        <button class="icon-btn delete-btn" type="submit" title="Delete">
                                            🗑
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr data-empty="true">
                            <td colspan="8" class="muted" style="text-align:center;padding:24px">
                                No transaction heads found. Add your first transaction head using the form on the right.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>

            <div class="table-footer">
                <span id="resultCount">
                    Showing {{ $transactionHeads->count() }} of {{ $transactionHeads->count() }} entries
                </span>

                <div class="pagination">
                    <button class="page-btn" type="button">‹</button>
                    <button class="page-btn active" type="button">1</button>
                    <button class="page-btn" type="button">›</button>
                </div>
            </div>
        </div>
    </div>

    <aside class="right-stack">
<div class="card form-panel">
            <div class="panel-head">
                <h3>Create / Edit Transaction Head</h3>
                <span class="muted">×</span>
            </div>

            <form
                class="form-grid"
                id="headForm"
                data-frontend-form
                data-action="{{ route('api.transaction-heads.store') }}"
                data-store-url="{{ route('api.transaction-heads.store') }}"
                data-success="Transaction head saved successfully."
            >
                @csrf

                <input type="hidden" name="_method" id="headFormMethod" value="POST">

                <div>
                    <label>Transaction Head Name <span class="required">*</span></label>
                    <input
                        name="name"
                        placeholder="Enter head name"
                        required
                    >
                </div>

                <div>
                    <label>Nature <span class="required">*</span></label>
                    <select
                        name="nature"
                        required
                        data-dropdown="/api/dropdowns/transaction-head-natures"
                        data-placeholder="Select Nature"
                    ></select>
                </div>

                <div>
                    <label>Default Party Type</label>
                    <select
                        name="default_party_type_id"
                        data-dropdown="/api/dropdowns/party-types"
                        data-placeholder="Select Party Type"
                    ></select>
                </div>

                <div class="two-col">
                    <div class="switch-row">
                        <span class="switch-label">Requires Party <span class="required">*</span></span>
                        <input type="hidden" id="requiresParty" name="requires_party" value="1">
                        <div class="switch on" id="requiresPartySwitch" data-input="requiresParty"></div>
                    </div>

                    <div class="switch-row">
                        <span class="switch-label">Requires Reference</span>
                        <input type="hidden" id="requiresReference" name="requires_reference" value="0">
                        <div class="switch" id="requiresReferenceSwitch" data-input="requiresReference"></div>
                    </div>
                </div>

                <div>
                    <label>Description</label>
                    <textarea
                        name="description"
                        placeholder="Enter description optional"
                    ></textarea>
                </div>

                <div>
                    <label>Status <span class="required">*</span></label>
                    <select name="status" required>
                        <option value="Active">Active</option>
                        <option value="Inactive">Inactive</option>
                    </select>
                </div>

                <div>
                    <label>Allowed Settlement Types <span class="required">*</span></label>

                    <input
                        type="hidden"
                        id="settlementTypes"
                        name="allowed_settlement_types"
                        value="[]"
                    >

                    <div
                        class="multi-select"
                        data-multi-select
                        data-input="settlementTypes"
                        data-required="true"
                    >
                        @foreach($settlementTypes as $settlementType)
                            <span
                                class="select-chip"
                                data-value="{{ $settlementType->name }}"
                            >
                                {{ $settlementType->name }}
                            </span>
                        @endforeach
                    </div>

                    <div class="hint">Select at least one settlement type.</div>
                </div>

                <div class="hint-box">
                    <strong>Backend note</strong>
                    All dropdowns and settlement types are loaded from backend data.
                </div>

                <div class="form-actions">
                    <button type="button" class="btn-ghost" id="cancelHeadBtn">Cancel</button>
                    <button type="submit" class="btn-primary">Save Head</button>
                </div>
            </form>
        </div>
    </aside>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const form = document.getElementById('headForm');

    if (!form) {
        return;
    }

    const methodInput = document.getElementById('headFormMethod');
    const addButton = document.getElementById('addHeadBtn');
    const cancelButton = document.getElementById('cancelHeadBtn');

    const name = form.querySelector('[name="name"]');
    const nature = form.querySelector('[name="nature"]');
    const defaultPartyType = form.querySelector('[name="default_party_type_id"]');
    const requiresPartyInput = document.getElementById('requiresParty');
    const requiresPartySwitch = document.getElementById('requiresPartySwitch');
    const requiresReferenceInput = document.getElementById('requiresReference');
    const requiresReferenceSwitch = document.getElementById('requiresReferenceSwitch');
    const description = form.querySelector('[name="description"]');
    const status = form.querySelector('[name="status"]');
    const settlementInput = document.getElementById('settlementTypes');
    const settlementBox = form.querySelector('[data-multi-select]');

    function showToast(message) {
        if (window.AccountingUI?.showToast) {
            window.AccountingUI.showToast(message);
            return;
        }

        alert(message);
    }

    function setDropdownValue(select, value) {
        if (!select) {
            return;
        }

        select.dataset.selected = value || '';
        select.value = value || '';

        if (select.dataset.dropdown && window.AccountingUI?.loadSelect) {
            window.AccountingUI.loadSelect(select).then(() => {
                select.value = value || '';
            });
        }
    }

    function setSwitch(input, switchElement, value) {
        const enabled = Number(value) === 1;

        input.value = enabled ? '1' : '0';
        switchElement.classList.toggle('on', enabled);
    }

    function syncSettlementTypes() {
        const values = Array.from(settlementBox.querySelectorAll('.select-chip.selected'))
            .map((chip) => chip.dataset.value || chip.textContent.trim());

        settlementInput.value = JSON.stringify(values);
        settlementBox.dataset.selectedCount = String(values.length);
    }

    function setSettlementTypes(values) {
        settlementBox.querySelectorAll('.select-chip').forEach((chip) => {
            const value = chip.dataset.value || chip.textContent.trim();
            chip.classList.toggle('selected', values.includes(value));
        });

        syncSettlementTypes();
    }

    function resetForm() {
        form.reset();
        form.dataset.action = form.dataset.storeUrl;
        methodInput.value = 'POST';

        nature.dataset.selected = '';
        defaultPartyType.dataset.selected = '';

        setSwitch(requiresPartyInput, requiresPartySwitch, 1);
        setSwitch(requiresReferenceInput, requiresReferenceSwitch, 0);
        setSettlementTypes([]);

        name.focus();
    }

    function loadForEdit(row) {
        let settlements = [];

        try {
            settlements = JSON.parse(row.dataset.settlements || '[]');
        } catch (error) {
            settlements = [];
        }

        form.dataset.action = row.dataset.updateUrl;
        methodInput.value = 'PUT';

        name.value = row.dataset.name || '';
        description.value = row.dataset.description || '';
        status.value = row.dataset.status || 'Active';

        setDropdownValue(nature, row.dataset.nature || '');
        setDropdownValue(defaultPartyType, row.dataset.party || '');
        setSwitch(requiresPartyInput, requiresPartySwitch, row.dataset.requiresParty || 0);
        setSwitch(requiresReferenceInput, requiresReferenceSwitch, row.dataset.requiresReference || 0);
        setSettlementTypes(settlements);

        form.scrollIntoView({ behavior: 'smooth', block: 'center' });
        showToast('Transaction head loaded for editing.');
    }

    settlementBox.querySelectorAll('.select-chip').forEach((chip) => {
        chip.addEventListener('click', syncSettlementTypes);
    });

    document.querySelectorAll('#headTable .edit-btn').forEach((button) => {
        button.addEventListener('click', () => loadForEdit(button.closest('tr')));
    });

    addButton.addEventListener('click', () => {
        resetForm();
        showToast('Ready to add a transaction head.');
    });

    cancelButton.addEventListener('click', () => {
        resetForm();
        showToast('Form cleared.');
    });
});
</script>

@endsection
