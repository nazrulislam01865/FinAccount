@extends('layouts.app')

@section('title', 'Party / Person Setup | Accounting System')

@section('content')
<div class="page-title">
    <div>
        <span class="page-label">Party / Person Setup</span>
        <h2>Party / Person Setup</h2>
        <p>Manage employees, suppliers, customers, drivers, tenants, owners, and other parties.</p>
    </div>
</div>

@include('partials.setup-progress', ['current' => 4])

<div class="layout">
    <div class="left-stack">
        <div class="card toolbar" data-table-filter="#partyTable" data-count-target="#resultCount">
            <div class="field search-field">
                <span>⌕</span>
                <input
                    placeholder="Search parties by name, mobile, email..."
                    data-filter-key="text"
                >
            </div>

            <div>
                <label>Party Type</label>
                <select
                    data-filter-key="type"
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

            <button
                class="btn-primary"
                type="button"
                id="addPartyBtn"
            >
                + Add Party
            </button>
        </div>

        <div class="card table-card">
            <table id="partyTable">
                <thead>
                    <tr>
                        <th>Party ID</th>
                        <th>Name</th>
                        <th>Party Type</th>
                        <th>Mobile</th>
                        <th>Email</th>
                        <th>Linked Ledger / Balance</th>
                        <th>Status</th>
                        <th style="text-align:right">Actions</th>
                    </tr>
                </thead>

                <tbody>
                    @forelse($parties as $party)
                        <tr
                            data-id="{{ $party->id }}"
                            data-name="{{ e($party->party_name) }}"
                            data-type="{{ $party->party_type_id }}"
                            data-mobile="{{ $party->mobile }}"
                            data-email="{{ $party->email }}"
                            data-address="{{ e($party->address) }}"
                            data-linked-ledger="{{ $party->linked_ledger_account_id }}"
                            data-opening-balance="{{ number_format((float) $party->opening_balance, 2, '.', '') }}"
                            data-status="{{ $party->status }}"
                            data-update-url="{{ url('/api/parties/' . $party->id) }}"
                        >
                            <td class="code">{{ $party->party_code }}</td>

                            <td class="strong">{{ $party->party_name }}</td>

                            <td>
                                <span class="badge badge-success">
                                    {{ $party->partyType?->name ?? '—' }}
                                </span>
                            </td>

                            <td class="{{ $party->mobile ? '' : 'muted' }}">
                                {{ $party->mobile ?: '—' }}
                            </td>

                            <td class="{{ $party->email ? '' : 'muted' }}">
                                {{ $party->email ?: '—' }}
                            </td>

                            <td>
                                <strong>{{ $party->linkedLedger?->display_name ?? '—' }}</strong>
                                <span class="hint">
                                    BDT {{ number_format((float) $party->opening_balance, 2) }}
                                </span>
                            </td>

                            <td>
                                <span class="badge {{ $party->status === 'Active' ? 'badge-success' : 'badge-neutral' }}">
                                    {{ $party->status }}
                                </span>
                            </td>

                            <td>
                                <div class="action-cell">
                                    <button
                                        class="icon-btn edit-btn"
                                        type="button"
                                        title="Edit"
                                    >
                                        ✎
                                    </button>

                                    <form
                                        method="POST"
                                        data-delete-form
                                        action="{{ url('/setup/parties/' . $party->id) }}"
                                        onsubmit="return confirm('Delete this party?')"
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
                                No parties found. Add your first party using the form on the right.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>

            <div class="table-footer">
                <span id="resultCount">Showing {{ $parties->count() }} of {{ $parties->count() }} entries</span>

                <div class="pagination">
                    <button class="page-btn" type="button">‹</button>
                    <button class="page-btn active" type="button">1</button>
                    <button class="page-btn" type="button">›</button>
                </div>
            </div>
        </div>

        <div class="card info-card">
            <h3>Parties can be used as sub-ledgers</h3>
            <p>Create parties and link them with ledger accounts to track customers, suppliers, employees, landlords, drivers, and tenants individually.</p>
        </div>
    </div>

    <aside class="right-stack">
<div class="card form-panel">
            <div class="panel-head">
                <h3>Create / Edit Party</h3>
                <span class="muted">×</span>
            </div>

            <form
                class="form-grid"
                id="partyForm"
                data-frontend-form
                data-action="{{ route('api.parties.store') }}"
                data-store-url="{{ route('api.parties.store') }}"
                data-success="Party saved successfully."
            >
                @csrf

                <input type="hidden" name="_method" id="partyFormMethod" value="POST">

                <div>
                    <label>Party Name <span class="required">*</span></label>
                    <input
                        name="party_name"
                        placeholder="Enter party name"
                        required
                    >
                </div>

                <div>
                    <label>Party Type <span class="required">*</span></label>
                    <select
                        name="party_type_id"
                        required
                        data-dropdown="/api/dropdowns/party-types"
                        data-placeholder="Select Party Type"
                    ></select>
                </div>



                <div class="two-col">
                    <div>
                        <label>Mobile</label>
                        <input
                            name="mobile"
                            type="text"
                            inputmode="tel"
                            maxlength="50"
                            placeholder="Enter mobile number"
                        >
                        <div class="hint">Optional text field.</div>
                    </div>

                    <div>
                        <label>Email</label>
                        <input
                            name="email"
                            type="email"
                            placeholder="Enter email address"
                        >
                    </div>
                </div>

                <div>
                    <label>Address</label>
                    <textarea
                        name="address"
                        placeholder="Enter full address"
                    ></textarea>
                </div>

                <div>
                    <label>Opening Balance</label>
                    <div class="currency-row">
                        <div class="prefix-box">BDT</div>
                        <input
                            name="opening_balance"
                            type="number"
                            value="0.00"
                            step="0.01"
                            min="0"
                        >
                    </div>
                    <div class="hint">Opening debit/credit balances can also be controlled from Opening Balance Setup.</div>
                </div>

                <div>
                    <label>Linked Ledger / Group <span class="required">*</span></label>
                    <select
                        name="linked_ledger_account_id"
                        required
                        data-dropdown="/api/dropdowns/ledger-accounts"
                        data-label="account"
                        data-placeholder="Select Ledger"
                    ></select>
                </div>



                <div>
                    <label>Status <span class="required">*</span></label>
                    <select name="status" required>
                        <option value="Active">Active</option>
                        <option value="Inactive">Inactive</option>
                    </select>
                </div>

                <div class="hint-box">
                    <strong>Backend note</strong>
                    Party ID is generated automatically like P-00001. Party Type and Linked Ledger are loaded from backend master data.
                </div>

                <div class="form-actions">
                    <button
                        type="button"
                        class="btn-ghost"
                        id="cancelPartyBtn"
                    >
                        Cancel
                    </button>

                    <button type="submit" class="btn-primary">
                        Save Party
                    </button>
                </div>
            </form>
        </div>
    </aside>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const form = document.getElementById('partyForm');

    if (!form) {
        return;
    }

    const methodInput = document.getElementById('partyFormMethod');
    const addButton = document.getElementById('addPartyBtn');
    const cancelButton = document.getElementById('cancelPartyBtn');

    const partyName = form.querySelector('[name="party_name"]');
    const partyType = form.querySelector('[name="party_type_id"]');
    const mobile = form.querySelector('[name="mobile"]');
    const email = form.querySelector('[name="email"]');
    const address = form.querySelector('[name="address"]');
    const openingBalance = form.querySelector('[name="opening_balance"]');
    const linkedLedger = form.querySelector('[name="linked_ledger_account_id"]');
    const status = form.querySelector('[name="status"]');

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

    function resetForm() {
        form.reset();
        form.dataset.action = form.dataset.storeUrl;
        methodInput.value = 'POST';

        [partyType, linkedLedger].forEach((select) => {
            if (select) {
                select.dataset.selected = '';
            }
        });

        openingBalance.value = '0.00';
        partyName.focus();
    }

    function loadForEdit(row) {
        form.dataset.action = row.dataset.updateUrl;
        methodInput.value = 'PUT';

        partyName.value = row.dataset.name || '';
        mobile.value = row.dataset.mobile || '';
        email.value = row.dataset.email || '';
        address.value = row.dataset.address || '';
        openingBalance.value = row.dataset.openingBalance || '0.00';
        status.value = row.dataset.status || 'Active';

        setDropdownValue(partyType, row.dataset.type || '');
        setDropdownValue(linkedLedger, row.dataset.linkedLedger || '');

        form.scrollIntoView({ behavior: 'smooth', block: 'center' });
        showToast('Party loaded for editing.');
    }

    document.querySelectorAll('#partyTable .edit-btn').forEach((button) => {
        button.addEventListener('click', () => loadForEdit(button.closest('tr')));
    });

    addButton.addEventListener('click', () => {
        resetForm();
        showToast('Ready to add a new party.');
    });

    cancelButton.addEventListener('click', () => {
        resetForm();
        showToast('Form cleared.');
    });
});
</script>

@endsection
