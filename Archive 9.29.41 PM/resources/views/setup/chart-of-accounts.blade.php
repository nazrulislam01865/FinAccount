@extends('layouts.app')

@section('title', 'Chart of Accounts | Accounting System')

@section('content')
<div class="page-title">
    <div>
        <h2>Chart of Accounts</h2>
        <p>Create and organize ledger accounts for the accounting system.</p>
    </div>
</div>

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
                data-toast="Use the form on the right to add a new account."
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
                            data-type="{{ $account->account_type_id }}"
                            data-status="{{ $account->status }}"
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
                        <tr>
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
        @include('partials.setup-progress', ['current' => 2])

        <div class="card form-panel">
            <div class="panel-head">
                <h3>Create Account</h3>
                <span class="muted">Required fields marked *</span>
            </div>

            <form
                class="form-grid"
                data-frontend-form
                data-action="{{ route('api.chart-of-accounts.store') }}"
                data-success="Account saved successfully."
            >
                @csrf

                <div>
                    <label>Account Code <span class="required">*</span></label>
                    <input
                            name="account_code"
                            type="number"
                            inputmode="numeric"
                            min="1"
                            step="1"
                            placeholder="Example: 1000"
                            required
                    >
                    <div class="hint">User input. Must be unique.</div>
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

                <div class="switch-row">
                    <span class="switch-label">Is Cash/Bank Account?</span>

                    <input
                        type="hidden"
                        id="isCashBank"
                        name="is_cash_bank"
                        value="0"
                    >

                    <div class="switch" data-input="isCashBank"></div>
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
                    Account Code, Account Name, Account Type, and Status are required. Account Code must be unique.
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
                        Save Account
                    </button>
                </div>
            </form>
        </div>
    </aside>
</div>
@endsection
