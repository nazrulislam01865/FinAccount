@extends('layouts.app')

@section('title', 'Chart of Accounts | Accounting System')

@section('content')
<div class="page-title">
    <div>
        <span class="page-label">Chart of Accounts</span>
        <h2>Chart of Accounts</h2>
        <p>Create and organize ledger accounts for the accounting system.</p>
    </div>
</div>

@include('partials.setup-progress', ['current' => 2])

<div class="layout">
    <div class="left-stack">
        <div class="card toolbar" data-table-filter="#accountsTable" data-count-target="#resultCount">
            <div class="field search-field">
                <span>⌕</span>
                <input
                    type="text"
                    placeholder="Search accounts..."
                    data-filter-key="text"
                >
            </div>

            <div>
                <label>Account Type</label>
                <select
                    data-filter-key="type"
                    data-dropdown="/api/dropdowns/account-types"
                    data-placeholder="All Types"
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
                id="addAccountBtn"
            >
                + Add New Account
            </button>
        </div>

        <div class="card table-card">
            <table id="accountsTable">
                <thead>
                    <tr>
                        <th>Account Code</th>
                        <th>Account Name</th>
                        <th>Parent Account</th>
                        <th>Account Type</th>
                        <th>Cash/Bank?</th>
                        <th>Status</th>
                        <th style="text-align:right">Actions</th>
                    </tr>
                </thead>

                <tbody>
                    @forelse($accounts as $account)
                        <tr
                            data-id="{{ $account->id }}"
                            data-account-code="{{ $account->account_code }}"
                            data-account-name="{{ e($account->account_name) }}"
                            data-type="{{ $account->account_type_id }}"
                            data-parent="{{ $account->parent_id }}"
                            data-is-cash-bank="{{ $account->is_cash_bank ? 1 : 0 }}"
                            data-description="{{ e($account->description) }}"
                            data-status="{{ $account->status }}"
                            data-update-url="{{ url('/api/chart-of-accounts/' . $account->id) }}"
                        >
                            <td class="code">{{ $account->account_code }}</td>

                            <td class="strong">{{ $account->account_name }}</td>

                            <td class="{{ $account->parent ? '' : 'muted' }}">
                                {{ $account->parent?->display_name ?? '—' }}
                            </td>

                            <td>{{ $account->accountType?->name ?? '—' }}</td>

                            <td>
                                @if($account->is_cash_bank)
                                    <span class="badge badge-success">Yes</span>
                                @else
                                    <span class="badge badge-neutral">No</span>
                                @endif
                            </td>

                            <td>
                                <span class="badge {{ $account->status === 'Active' ? 'badge-success' : 'badge-neutral' }}">
                                    {{ $account->status }}
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
                                        action="{{ url('/setup/chart-of-accounts/' . $account->id) }}"
                                        onsubmit="return confirm('Delete this account?')"
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
                            <td colspan="7" class="muted" style="text-align:center;padding:24px">
                                No chart of accounts found. Add your first account using the form on the right.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>

            <div class="table-footer">
                <span id="resultCount">Showing {{ $accounts->count() }} of {{ $accounts->count() }} entries</span>

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
                <h3 id="accountFormTitle">Create Account</h3>
                <span class="muted">Required fields marked *</span>
            </div>

            <form
                class="form-grid"
                id="accountForm"
                data-frontend-form
                data-action="{{ route('api.chart-of-accounts.store') }}"
                data-store-url="{{ route('api.chart-of-accounts.store') }}"
                data-success="Account saved successfully."
            >
                @csrf

                <input type="hidden" name="_method" id="accountFormMethod" value="POST">

                <div>
                    <label>Account Code <span class="required">*</span></label>
                    <input
                            name="account_code"
                            type="text"
                            maxlength="50"
                            placeholder="Example: 1000"
                            required
                    >
                    <div class="hint">Text code. Must be unique.</div>
                </div>

                <div>
                    <label>Account Name <span class="required">*</span></label>
                    <input
                        name="account_name"
                        placeholder="Example: Cash"
                        required
                    >
                </div>

                <div>
                    <label>Account Type <span class="required">*</span></label>
                    <select
                        name="account_type_id"
                        required
                        data-controls-parent-account
                        data-dropdown="/api/dropdowns/account-types"
                        data-placeholder="Select Account Type"
                    ></select>
                </div>

                <div>
                    <label>Parent Account</label>
                    <select
                        name="parent_id"
                        data-parent-account-select
                        data-base-url="/api/dropdowns/parent-accounts"
                        data-dropdown="/api/dropdowns/parent-accounts"
                        data-label="account"
                        data-placeholder="None"
                    ></select>
                    <div class="hint">Optional. Loaded from existing accounts.</div>
                </div>



                <div class="switch-row">
                    <span class="switch-label">Is Cash/Bank Account?</span>

                    <input
                        type="hidden"
                        id="isCashBank"
                        name="is_cash_bank"
                        value="0"
                    >

                    <div class="switch" id="isCashBankSwitch" data-input="isCashBank"></div>
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

                <div class="hint-box">
                    <strong>Backend validation</strong>
                    Account Code, Account Name, Account Type, and Status are required. Opening balances are entered on the Opening Balance Setup page.
                </div>

                <div class="form-actions">
                    <button
                        type="button"
                        class="btn-ghost"
                        id="cancelAccountBtn"
                    >
                        Cancel
                    </button>

                    <button type="submit" class="btn-primary">
                        Save Account
                    </button>
                </div>
            </form>
        </div>
    </aside>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const form = document.getElementById('accountForm');

    if (!form) {
        return;
    }

    const title = document.getElementById('accountFormTitle');
    const methodInput = document.getElementById('accountFormMethod');
    const addButton = document.getElementById('addAccountBtn');
    const cancelButton = document.getElementById('cancelAccountBtn');
    const cashBankInput = document.getElementById('isCashBank');
    const cashBankSwitch = document.getElementById('isCashBankSwitch');

    const accountCode = form.querySelector('[name="account_code"]');
    const accountName = form.querySelector('[name="account_name"]');
    const accountType = form.querySelector('[name="account_type_id"]');
    const parentAccount = form.querySelector('[name="parent_id"]');
    const description = form.querySelector('[name="description"]');
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

    function setCashBankSwitch(value) {
        const enabled = Number(value) === 1;

        cashBankInput.value = enabled ? '1' : '0';
        cashBankSwitch.classList.toggle('on', enabled);
    }

    function resetForm() {
        form.reset();
        form.dataset.action = form.dataset.storeUrl;
        methodInput.value = 'POST';
        title.textContent = 'Create Account';

        accountType.dataset.selected = '';
        parentAccount.dataset.selected = '';
        parentAccount.dataset.excludeId = '';

        setCashBankSwitch(0);
        accountCode.focus();
    }

    function loadForEdit(row) {
        form.dataset.action = row.dataset.updateUrl;
        methodInput.value = 'PUT';
        title.textContent = 'Edit Account';

        accountCode.value = row.dataset.accountCode || '';
        accountName.value = row.dataset.accountName || '';
        description.value = row.dataset.description || '';
        status.value = row.dataset.status || 'Active';

        setCashBankSwitch(row.dataset.isCashBank || 0);
        setDropdownValue(accountType, row.dataset.type || '');

        parentAccount.dataset.excludeId = row.dataset.id || '';
        setDropdownValue(parentAccount, row.dataset.parent || '');

        form.scrollIntoView({ behavior: 'smooth', block: 'center' });
        showToast('Account loaded for editing.');
    }

    document.querySelectorAll('#accountsTable .edit-btn').forEach((button) => {
        button.addEventListener('click', () => loadForEdit(button.closest('tr')));
    });

    addButton.addEventListener('click', () => {
        resetForm();
        showToast('Ready to add a new account.');
    });

    cancelButton.addEventListener('click', () => {
        resetForm();
        showToast('Form cleared.');
    });
});
</script>

@endsection
