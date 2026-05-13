@extends('layouts.app')

@section('title', 'Cash / Bank Account Setup | Accounting System')

@section('content')
<div class="page-title">
    <div>
        <span class="page-label">Cash / Bank Account Setup</span>
        <h2>Cash / Bank Account Setup</h2>
        <p>Configure cash boxes, bank accounts, and mobile financial accounts.</p>
    </div>
</div>

@include('partials.setup-progress', ['current' => 3])

<div class="layout">
    <div class="left-stack">
        <div class="card toolbar" data-table-filter="#cashBankTable" data-count-target="#resultCount">
            <div class="field search-field">
                <span>⌕</span>
                <input
                    type="text"
                    placeholder="Search cash/bank accounts..."
                    data-filter-key="text"
                >
            </div>

            <div>
                <label>Type</label>
                <select
                    data-filter-key="type"
                    data-dropdown="/api/dropdowns/cash-bank-account-types"
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
                id="addCashBankBtn"
            >
                + Add Cash / Bank
            </button>
        </div>

        <div class="card table-card">
            <table id="cashBankTable">
                <thead>
                    <tr>
                        <th>Account Name</th>
                        <th>Type</th>
                        <th>Linked Ledger Account</th>
                        <th>Bank Name</th>
                        <th>Branch Name</th>
                        <th>Account Number</th>
                        <th>Opening Balance</th>
                        <th>Status</th>
                        <th style="text-align:right">Actions</th>
                    </tr>
                </thead>

                <tbody>
                    @forelse($accounts as $account)
                        <tr
                            data-id="{{ $account->id }}"
                            data-name="{{ e($account->cash_bank_name) }}"
                            data-type="{{ $account->type }}"
                            data-linked-ledger="{{ $account->linked_ledger_account_id }}"
                            data-bank-name="{{ e($account->bank_name ?? $account->bank?->bank_name) }}"
                            data-branch="{{ e($account->branch_name) }}"
                            data-account-number="{{ $account->account_number }}"
                            data-opening-balance="{{ number_format((float) $account->opening_balance, 2, '.', '') }}"
                            data-status="{{ $account->status }}"
                            data-update-url="{{ url('/api/cash-bank-accounts/' . $account->id) }}"
                        >
                            <td class="strong">{{ $account->cash_bank_name }}</td>

                            <td>
                                <span class="badge badge-blue">{{ $account->type }}</span>
                            </td>

                            <td>
                                {{ $account->linkedLedger?->display_name ?? '—' }}
                            </td>

                            <td class="{{ ($account->bank_name ?? $account->bank?->bank_name) ? '' : 'muted' }}">
                                {{ $account->bank_name ?? $account->bank?->display_name ?? $account->bank?->bank_name ?? '—' }}
                            </td>

                            <td class="{{ $account->branch_name ? '' : 'muted' }}">
                                {{ $account->branch_name ?: '—' }}
                            </td>

                            <td class="{{ $account->account_number ? '' : 'muted' }}">
                                {{ $account->account_number ?: '—' }}
                            </td>

                            <td>
                                BDT {{ number_format((float) $account->opening_balance, 2) }}
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
                                        action="{{ url('/setup/cash-bank-accounts/' . $account->id) }}"
                                        onsubmit="return confirm('Delete this cash/bank account?')"
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
                                No cash/bank accounts found. Add your first account using the form on the right.
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
                <h3 id="cashBankFormTitle">Create Cash / Bank Account</h3>
                <span class="muted">Required fields marked *</span>
            </div>

            <form
                class="form-grid"
                id="cashBankForm"
                data-frontend-form
                data-action="{{ route('api.cash-bank-accounts.store') }}"
                data-store-url="{{ route('api.cash-bank-accounts.store') }}"
                data-success="Cash / Bank account saved successfully."
            >
                @csrf

                <input type="hidden" name="_method" id="cashBankFormMethod" value="POST">

                <div>
                    <label>Cash/Bank Account Name <span class="required">*</span></label>
                    <input
                        name="cash_bank_name"
                        placeholder="Example: Office Cash / BRAC Bank Main"
                        required
                    >
                </div>

                <div>
                    <label>Type <span class="required">*</span></label>
                    <select
                        id="cashBankType"
                        name="type"
                        required
                        data-dropdown="/api/dropdowns/cash-bank-account-types"
                        data-placeholder="Select Type"
                    ></select>
                </div>

                <div>
                    <label>Linked Ledger Account <span class="required">*</span></label>
                    <select
                        name="linked_ledger_account_id"
                        required
                        data-dropdown="/api/dropdowns/cash-bank-ledgers"
                        data-label="account"
                        data-placeholder="Select Linked Ledger Account"
                    ></select>
                    <div class="hint">Shows active Chart of Accounts marked as Cash/Bank.</div>
                </div>

                <div id="bankFields">
                    <label>Bank Name <span class="required bank-required">*</span></label>
                    <input
                        id="bankId"
                        name="bank_name"
                        placeholder="Example: BRAC Bank PLC"
                    >
                    <div class="hint">Required only when Type is Bank. Enter the bank or MFS provider name.</div>
                </div>

                <div>
                    <label>Branch Name</label>
                    <input
                        name="branch_name"
                        placeholder="Enter branch name"
                    >
                </div>

                <div>
                    <label>Account Number</label>
                    <input
                        name="account_number"
                        type="text"
                        inputmode="numeric"
                        placeholder="Enter account number"
                    >
                    <div class="hint">Optional text field as defined in the PRD.</div>
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
                    Cash/Bank Account Name, Type, Linked Ledger Account, and Status are required.
                    Bank Name is required only when Type is Bank. Account Number is optional.
                </div>

                <div class="form-actions">
                    <button
                        type="button"
                        class="btn-ghost"
                        id="cancelCashBankBtn"
                    >
                        Cancel
                    </button>

                    <button type="submit" class="btn-primary">
                        Save Cash / Bank
                    </button>
                </div>
            </form>
        </div>
    </aside>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const form = document.getElementById('cashBankForm');

    if (!form) {
        return;
    }

    const title = document.getElementById('cashBankFormTitle');
    const methodInput = document.getElementById('cashBankFormMethod');
    const addButton = document.getElementById('addCashBankBtn');
    const cancelButton = document.getElementById('cancelCashBankBtn');

    const name = form.querySelector('[name="cash_bank_name"]');
    const type = form.querySelector('[name="type"]');
    const linkedLedger = form.querySelector('[name="linked_ledger_account_id"]');
    const bank = form.querySelector('[name="bank_name"]');
    const branch = form.querySelector('[name="branch_name"]');
    const accountNumber = form.querySelector('[name="account_number"]');
    const openingBalance = form.querySelector('[name="opening_balance"]');
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
                select.dispatchEvent(new Event('change', { bubbles: true }));
            });
        }
    }

    function resetForm() {
        form.reset();
        form.dataset.action = form.dataset.storeUrl;
        methodInput.value = 'POST';
        title.textContent = 'Create Cash / Bank Account';

        [type, linkedLedger].forEach((select) => {
            if (select) {
                select.dataset.selected = '';
            }
        });

        if (bank) {
            bank.value = '';
        }

        openingBalance.value = '0.00';
        type.dispatchEvent(new Event('change', { bubbles: true }));
        name.focus();
    }

    function loadForEdit(row) {
        form.dataset.action = row.dataset.updateUrl;
        methodInput.value = 'PUT';
        title.textContent = 'Edit Cash / Bank Account';

        name.value = row.dataset.name || '';
        branch.value = row.dataset.branch || '';
        accountNumber.value = row.dataset.accountNumber || '';
        openingBalance.value = row.dataset.openingBalance || '0.00';
        status.value = row.dataset.status || 'Active';

        setDropdownValue(type, row.dataset.type || '');
        setDropdownValue(linkedLedger, row.dataset.linkedLedger || '');
        if (bank) {
            bank.value = row.dataset.bankName || '';
        }

        form.scrollIntoView({ behavior: 'smooth', block: 'center' });
        showToast('Cash / Bank account loaded for editing.');
    }

    document.querySelectorAll('#cashBankTable .edit-btn').forEach((button) => {
        button.addEventListener('click', () => loadForEdit(button.closest('tr')));
    });

    addButton.addEventListener('click', () => {
        resetForm();
        showToast('Ready to add a new cash/bank account.');
    });

    cancelButton.addEventListener('click', () => {
        resetForm();
        showToast('Form cleared.');
    });
});
</script>

@endsection
