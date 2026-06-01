@extends('layouts.app')

@section('title', 'Party / Person Setup | HisebGhor')

@push('styles')
<style>
    .setup-full-width-flow {
        grid-template-columns: 1fr !important;
        gap: 18px;
    }

    .setup-full-width-flow > .right-stack {
        order: 1;
        display: grid;
        grid-template-columns: 1fr !important;
        gap: 18px;
        width: 100%;
    }

    .setup-full-width-flow > .left-stack {
        order: 2;
        width: 100%;
    }

    .setup-full-width-flow .form-panel,
    .setup-full-width-flow .form-card,
    .setup-full-width-flow .toolbar,
    .setup-full-width-flow .table-card,
    .setup-full-width-flow .table-info-grid {
        width: 100%;
    }

    .setup-full-width-flow .form-grid {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 15px;
        align-items: start;
    }

    .setup-full-width-flow .form-grid > .two-col,
    .setup-full-width-flow .form-grid > .hint-box,
    .setup-full-width-flow .form-grid > .form-actions,
    .setup-full-width-flow .form-grid > .actions,
    .setup-full-width-flow .form-grid > .preview-box,
    .setup-full-width-flow .form-grid > #bankFields {
        grid-column: 1 / -1;
    }

    .setup-full-width-flow .form-actions,
    .setup-full-width-flow .actions {
        margin-top: 3px;
    }

    .setup-full-width-flow .toolbar {
        margin-top: 0;
    }

    .setup-full-width-flow .table-card {
        overflow-x: auto;
    }

    .setup-full-width-flow .table-wrap {
        width: 100%;
        overflow-x: scroll;
        scrollbar-gutter: stable both-edges;
    }

    .setup-full-width-flow table {
        min-width: 1100px;
    }

    .setup-full-width-flow #bankFields {
        display: grid;
        grid-template-columns: repeat(3, minmax(0, 1fr));
        gap: 15px;
    }

    .setup-full-width-flow #bankFields.hidden,
    .setup-full-width-flow #bankFields .hidden {
        display: none !important;
    }

    @media (max-width: 880px) {
        .setup-full-width-flow .form-grid,
        .setup-full-width-flow #bankFields {
            grid-template-columns: 1fr;
        }
    }
</style>
@endpush

@section('content')
<div class="page-title">
    <div>
        <span class="page-label">Party / Person Setup</span>
        <h2>Party / Person Setup</h2>
        <p>Manage customers, suppliers, employees, drivers, tenants, owners, and other parties as accounting sub-ledgers.</p>
    </div>
</div>

@include('partials.setup-progress', ['current' => 4])

<div class="layout setup-full-width-flow">
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
            <table id="partyTable" data-client-pagination="true" data-page-size="10">
                <thead>
                    <tr>
                        <th>Party ID</th>
                        <th>Name</th>
                        <th>Party Type</th>
                        <th>Mobile</th>
                        <th>Email</th>
                        <th>Ledger Nature</th>
                        <th>Linked Ledger</th>
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
                            data-sub-type="{{ e($party->sub_type) }}"
                            data-mobile="{{ e($party->mobile) }}"
                            data-email="{{ e($party->email) }}"
                            data-address="{{ e($party->address) }}"
                            data-credit-limit="{{ $party->credit_limit !== null ? number_format((float) $party->credit_limit, 2, '.', '') : '' }}"
                            data-payment-terms="{{ e($party->payment_terms) }}"
                            data-department="{{ e($party->department) }}"
                            data-designation="{{ e($party->designation) }}"
                            data-salary-amount="{{ $party->salary_amount !== null ? number_format((float) $party->salary_amount, 2, '.', '') : '' }}"
                            data-ownership-percentage="{{ $party->ownership_percentage !== null ? number_format((float) $party->ownership_percentage, 2, '.', '') : '' }}"
                            data-contact-info="{{ e($party->contact_info) }}"
                            data-linked-ledger="{{ $party->linked_ledger_account_id }}"
                            data-default-ledger-nature="{{ $party->default_ledger_nature }}"
                            data-notes="{{ e($party->notes) }}"
                            data-status="{{ $party->status }}"
                            data-update-url="{{ url('/api/parties/' . $party->id) }}"
                        >
                            <td class="code">{{ $party->party_code }}</td>

                            <td class="strong">{{ $party->party_name }}</td>

                            <td data-type="{{ $party->party_type_id }}">
                                <span class="badge badge-success">
                                    {{ $party->partyType?->name ?? '—' }}
                                </span>
                            </td>

                            <td class="{{ $party->mobile ? '' : 'muted' }}">
                                {{ $party->mobile ?: '—' }}
                            </td>

                            <td class="{{ $party->email ? '' : 'muted' }}">
                                {{ $party->email ?: '—' }}
                                @if($party->payment_terms || $party->credit_limit || $party->department || $party->designation || $party->ownership_percentage)
                                    <div class="hint" style="margin-top:2px">
                                        @if($party->credit_limit) Credit limit: BDT {{ number_format((float) $party->credit_limit, 2) }} @endif
                                        @if($party->payment_terms) Terms: {{ $party->payment_terms }} @endif
                                        @if($party->department) Dept: {{ $party->department }} @endif
                                        @if($party->designation) · {{ $party->designation }} @endif
                                        @if($party->ownership_percentage) Ownership: {{ number_format((float) $party->ownership_percentage, 2) }}% @endif
                                    </div>
                                @endif
                            </td>

                            <td>
                                <span class="badge badge-blue">
                                    {{ $party->default_ledger_nature ?: 'No Effect' }}
                                </span>

                            </td>

                            <td>
                                <strong>{{ $party->linkedLedger?->display_name ?? '—' }}</strong>
                                @if($party->linkedLedger?->accountType)
                                    <div class="hint" style="margin-top:2px">
                                        {{ $party->linkedLedger->accountType->name }}
                                        · {{ $party->linkedLedger->normal_balance ?: $party->linkedLedger->accountType->normal_balance }}
                                    </div>
                                @endif
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
                            <td colspan="9" class="muted" style="text-align:center;padding:24px">
                                No parties found. Add your first party using the form above.
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
            <h3>Parties are sub-ledgers</h3>
            <p>The user selects a party in transactions. The backend uses the party type, linked ledger, and accounting rules to affect receivable, payable, salary payable, advance, or owner ledgers automatically.</p>
        </div>
    </div>

    <aside class="right-stack">
        <div class="card form-panel">
            <div class="panel-head">
                <h3 id="partyFormTitle">Create Party</h3>
                <span class="muted">Required fields marked *</span>
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
                <input type="hidden" name="default_ledger_nature" id="defaultLedgerNature" value="No Effect">

                <div>
                    <label>Party Name <span class="required">*</span></label>
                    <input
                        name="party_name"
                        placeholder="Example: Karim Agro Farm"
                        required
                    >
                </div>

                <div>
                    <label>Party Type <span class="required">*</span></label>
                    <select
                        name="party_type_id"
                        id="partyType"
                        required
                        data-dropdown="/api/dropdowns/party-types"
                        data-placeholder="Select Party Type"
                    ></select>
                    <div class="hint">Party Type suggests receivable, payable, owner, or no-effect handling.</div>
                </div>

                <div>
                    <label>Ledger Nature</label>
                    <input
                        type="text"
                        id="ledgerNaturePreview"
                        class="readonly-field"
                        value="Select Party Type first"
                        readonly
                    >
                    <div class="hint">Auto-filled so users do not choose debit or credit manually.</div>
                </div>

                <div>
                    <label>Sub Type</label>
                    <input
                        name="sub_type"
                        maxlength="100"
                        placeholder="Example: Farmer, Retailer, Service Provider"
                    >
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
                        <div class="hint">Optional but unique when provided.</div>
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

                <div class="party-profile-block party-profile-customer" style="display:none">
                    <label>Credit Limit</label>
                    <div class="currency-row">
                        <div class="prefix-box">BDT</div>
                        <input name="credit_limit" type="number" step="0.01" min="0" placeholder="Optional customer credit limit">
                    </div>
                </div>

                <div class="party-profile-block party-profile-customer party-profile-supplier" style="display:none">
                    <label>Payment Terms</label>
                    <input name="payment_terms" maxlength="100" placeholder="Example: 30 days">
                </div>

                <div class="party-profile-block party-profile-employee" style="display:none">
                    <label>Department</label>
                    <input name="department" maxlength="100" placeholder="Example: Sales">
                </div>

                <div class="party-profile-block party-profile-employee" style="display:none">
                    <label>Designation</label>
                    <input name="designation" maxlength="100" placeholder="Example: Officer">
                </div>

                <div class="party-profile-block party-profile-employee" style="display:none">
                    <label>Salary Amount</label>
                    <div class="currency-row">
                        <div class="prefix-box">BDT</div>
                        <input name="salary_amount" type="number" step="0.01" min="0" placeholder="Reference salary amount">
                    </div>
                </div>

                <div class="party-profile-block party-profile-owner" style="display:none">
                    <label>Ownership Percentage</label>
                    <input name="ownership_percentage" type="number" step="0.01" min="0" max="100" placeholder="0-100">
                </div>

                <div class="party-profile-block party-profile-owner" style="display:none">
                    <label>Contact Info</label>
                    <input name="contact_info" maxlength="255" placeholder="Owner/partner contact reference">
                </div>

                <div>
                    <label>Linked Ledger <span class="required">*</span></label>
                    <select
                        name="linked_ledger_account_id"
                        id="linkedLedger"
                        required
                        data-base-url="/api/dropdowns/ledger-accounts"
                        data-dropdown="/api/dropdowns/ledger-accounts?for_party=1"
                        data-label="account"
                        data-placeholder="Select Party Ledger"
                    ></select>
                    <div class="hint">Filtered by party type. Customer/Tenant uses Asset receivable. Supplier/Vendor/Employee uses Liability payable.</div>
                </div>


                <div>
                    <label>Notes</label>
                    <textarea
                        name="notes"
                        placeholder="Optional internal note"
                    ></textarea>
                </div>

                <div>
                    <label>Status <span class="required">*</span></label>
                    <select name="status" required>
                        <option value="Active">Active</option>
                        <option value="Inactive">Inactive</option>
                    </select>
                </div>

                <div class="hint-box">
                    <strong>Accounting safety rules</strong>
                    Party setup stores who is involved and which control ledger they belong to. It does not post income, expense, payable, or receivable by itself. Posting happens through transaction rules.
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

    const title = document.getElementById('partyFormTitle');
    const methodInput = document.getElementById('partyFormMethod');
    const addButton = document.getElementById('addPartyBtn');
    const cancelButton = document.getElementById('cancelPartyBtn');

    const partyName = form.querySelector('[name="party_name"]');
    const partyType = document.getElementById('partyType');
    const defaultLedgerNature = document.getElementById('defaultLedgerNature');
    const ledgerNaturePreview = document.getElementById('ledgerNaturePreview');
    const subType = form.querySelector('[name="sub_type"]');
    const mobile = form.querySelector('[name="mobile"]');
    const email = form.querySelector('[name="email"]');
    const address = form.querySelector('[name="address"]');
    const creditLimit = form.querySelector('[name="credit_limit"]');
    const paymentTerms = form.querySelector('[name="payment_terms"]');
    const department = form.querySelector('[name="department"]');
    const designation = form.querySelector('[name="designation"]');
    const salaryAmount = form.querySelector('[name="salary_amount"]');
    const ownershipPercentage = form.querySelector('[name="ownership_percentage"]');
    const contactInfo = form.querySelector('[name="contact_info"]');
    const linkedLedger = document.getElementById('linkedLedger');
    const notes = form.querySelector('[name="notes"]');
    const status = form.querySelector('[name="status"]');

    function showToast(message) {
        if (window.AccountingUI?.showToast) {
            window.AccountingUI.showToast(message);
            return;
        }

        alert(message);
    }

    function selectedPartyTypeOption() {
        return partyType?.selectedOptions?.[0] || null;
    }

    function setDropdownValue(select, value) {
        if (!select) {
            return Promise.resolve();
        }

        select.dataset.selected = value || '';
        select.value = value || '';

        if (select.dataset.dropdown && window.AccountingUI?.loadSelect) {
            return window.AccountingUI.loadSelect(select).then(() => {
                select.value = value || '';
                select.dispatchEvent(new Event('change', { bubbles: true }));
            });
        }

        select.dispatchEvent(new Event('change', { bubbles: true }));
        return Promise.resolve();
    }


    function syncNaturePreview() {
        const option = selectedPartyTypeOption();
        const nature = option?.dataset.defaultLedgerNature || defaultLedgerNature.value || 'No Effect';

        defaultLedgerNature.value = nature;
        ledgerNaturePreview.value = nature;
    }

    function syncPartyProfileFields() {
        const option = selectedPartyTypeOption();
        const label = `${option?.textContent || ''} ${option?.dataset.code || ''}`.toLowerCase();

        document.querySelectorAll('.party-profile-block').forEach((block) => {
            block.style.display = 'none';
        });

        const show = (selector) => document.querySelectorAll(selector).forEach((block) => {
            block.style.display = 'block';
        });

        if (label.includes('customer') || label.includes('cus') || label.includes('tenant')) {
            show('.party-profile-customer');
        }

        if (label.includes('supplier') || label.includes('sup') || label.includes('vendor') || label.includes('landlord')) {
            show('.party-profile-supplier');
        }

        if (label.includes('employee') || label.includes('driver')) {
            show('.party-profile-employee');
        }

        if (label.includes('owner') || label.includes('partner')) {
            show('.party-profile-owner');
        }
    }

    function reloadLedgerOptions(selectedValue = '') {
        const baseUrl = linkedLedger.dataset.baseUrl || '/api/dropdowns/ledger-accounts';
        const params = new URLSearchParams();

        params.set('for_party', '1');

        if (partyType.value) {
            params.set('party_type_id', partyType.value);
        }

        if (defaultLedgerNature.value) {
            params.set('ledger_nature', defaultLedgerNature.value);
        }

        linkedLedger.dataset.dropdown = `${baseUrl}?${params.toString()}`;

        return setDropdownValue(linkedLedger, selectedValue);
    }

    function maybeUseDefaultLedger() {
        const option = selectedPartyTypeOption();
        const defaultLedgerId = option?.dataset.defaultLedgerAccountId || '';

        if (defaultLedgerId && !linkedLedger.value) {
            setDropdownValue(linkedLedger, defaultLedgerId);
        }
    }

    function resetForm() {
        form.reset();
        form.dataset.action = form.dataset.storeUrl;
        methodInput.value = 'POST';
        title.textContent = 'Create Party';

        [partyType, linkedLedger].forEach((select) => {
            if (select) {
                select.dataset.selected = '';
            }
        });

        defaultLedgerNature.value = 'No Effect';
        ledgerNaturePreview.value = 'Select Party Type first';
        [creditLimit, paymentTerms, department, designation, salaryAmount, ownershipPercentage, contactInfo].forEach((input) => {
            if (input) input.value = '';
        });
        status.value = 'Active';

        syncPartyProfileFields();
        reloadLedgerOptions();
        partyName.focus();
    }

    function loadForEdit(row) {
        form.dataset.action = row.dataset.updateUrl;
        methodInput.value = 'PUT';
        title.textContent = 'Edit Party';

        partyName.value = row.dataset.name || '';
        subType.value = row.dataset.subType || '';
        mobile.value = row.dataset.mobile || '';
        email.value = row.dataset.email || '';
        address.value = row.dataset.address || '';
        creditLimit.value = row.dataset.creditLimit || '';
        paymentTerms.value = row.dataset.paymentTerms || '';
        department.value = row.dataset.department || '';
        designation.value = row.dataset.designation || '';
        salaryAmount.value = row.dataset.salaryAmount || '';
        ownershipPercentage.value = row.dataset.ownershipPercentage || '';
        contactInfo.value = row.dataset.contactInfo || '';
        defaultLedgerNature.value = row.dataset.defaultLedgerNature || 'No Effect';
        notes.value = row.dataset.notes || '';
        status.value = row.dataset.status || 'Active';

        setDropdownValue(partyType, row.dataset.type || '').then(() => {
            syncNaturePreview();
            syncPartyProfileFields();
            reloadLedgerOptions(row.dataset.linkedLedger || '');
        });

        form.scrollIntoView({ behavior: 'smooth', block: 'center' });
        showToast('Party loaded for editing.');
    }

    partyType.addEventListener('change', () => {
        const option = selectedPartyTypeOption();
        const defaultLedgerNatureValue = option?.dataset.defaultLedgerNature || 'No Effect';

        defaultLedgerNature.value = defaultLedgerNatureValue;
        syncNaturePreview();
        syncPartyProfileFields();
        reloadLedgerOptions().then(maybeUseDefaultLedger);
    });


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

    syncNaturePreview();
    syncPartyProfileFields();
});
</script>

@endsection
