@extends('layouts.app')

@section('title', 'Cash / Bank Account Setup | Accounting System')

@section('content')
<div class="page-title">
    <div>
        <h2>Cash / Bank Account Setup</h2>
        <p>Configure cash boxes, bank accounts, and mobile financial accounts.</p>
    </div>
</div>

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
                data-toast="Use the form on the right to add a new cash/bank account."
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
                            data-type="{{ $account->type }}"
                            data-status="{{ $account->status }}"
                        >
                            <td class="strong">{{ $account->cash_bank_name }}</td>

                            <td>
                                <span class="badge badge-blue">{{ $account->type }}</span>
                            </td>

                            <td>
                                {{ $account->linkedLedger?->display_name ?? '—' }}
                            </td>

                            <td class="{{ $account->bank ? '' : 'muted' }}">
                                {{ $account->bank?->display_name ?? $account->bank?->bank_name ?? '—' }}
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
                                        class="icon-btn"
                                        type="button"
                                        data-toast="Edit will be added later."
                                    >
                                        ✎
                                    </button>

                                    <button
                                        class="icon-btn"
                                        type="button"
                                        data-toast="More actions will be added later."
                                    >
                                        ⋮
                                    </button>
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
        @include('partials.setup-progress', ['current' => 3])

        <div class="card form-panel">
            <div class="panel-head">
                <h3>Create Cash / Bank Account</h3>
                <span class="muted">Required fields marked *</span>
            </div>

            <form
                class="form-grid"
                data-frontend-form
                data-action="{{ route('api.cash-bank-accounts.store') }}"
                data-success="Cash / Bank account saved successfully."
            >
                @csrf

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
                    <select
                        id="bankId"
                        name="bank_id"
                        data-dropdown="/api/dropdowns/banks"
                        data-label="bank"
                        data-placeholder="Select Bank"
                    ></select>
                    <div class="hint">Required only when Type is Bank.</div>
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
                        maxlength="13"
                        pattern="[0-9]{13}"
                        placeholder="13 digit account number"
                    >
                    <div class="hint">Optional, but if entered it must be exactly 13 digits.</div>
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
                    Bank Name is required only when Type is Bank. Account Number must be exactly 13 digits if provided.
                </div>

                <div class="form-actions">
                    <button
                        type="reset"
                        class="btn-ghost"
                        data-toast="Form cleared."
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
@endsection
