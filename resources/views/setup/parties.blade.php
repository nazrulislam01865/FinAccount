@extends('layouts.app')

@section('title', 'Party / Person Setup | Accounting System')

@section('content')
<div class="page-title">
    <div>
        <h2>Party / Person Setup</h2>
        <p>Manage employees, suppliers, customers, drivers, tenants, owners, and other parties.</p>
    </div>
</div>

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
                data-toast="Ready to add a new party."
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
                        <th>Linked Ledger / Opening Balance</th>
                        <th>Status</th>
                        <th style="text-align:right">Actions</th>
                    </tr>
                </thead>

                <tbody>
                    @forelse($parties as $party)
                        <tr
                            data-type="{{ $party->party_type_id }}"
                            data-status="{{ $party->status }}"
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
                                    @if($party->opening_balance_type)
                                        {{ $party->opening_balance_type === 'Debit' ? 'Dr' : 'Cr' }}
                                    @endif
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
        @include('partials.setup-progress', ['current' => 4])

        <div class="card form-panel">
            <div class="panel-head">
                <h3>Create / Edit Party</h3>
                <span class="muted">×</span>
            </div>

            <form
                class="form-grid"
                data-frontend-form
                data-action="{{ route('api.parties.store') }}"
                data-success="Party saved successfully."
            >
                @csrf

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

                <div>
                    <label>Contact Person</label>
                    <input
                        name="contact_person"
                        placeholder="Enter contact person name"
                    >
                </div>

                <div class="two-col">
                    <div>
                        <label>Mobile</label>
                        <input
                            name="mobile"
                            type="text"
                            inputmode="tel"
                            maxlength="15"
                            pattern="^\+8801[0-9]{3}-[0-9]{6}$"
                            placeholder="+8801XXX-XXXXXX"
                        >
                        <div class="hint">Format: +8801XXX-XXXXXX</div>
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
                        <input
                            name="opening_balance"
                            type="number"
                            value="0.00"
                            step="0.01"
                            min="0"
                        >

                        <select
                            name="opening_balance_type"
                            data-dropdown="/api/dropdowns/party-balance-types"
                            data-placeholder="None"
                        ></select>
                    </div>
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
                    <label>Notes</label>
                    <textarea
                        name="notes"
                        placeholder="Enter notes optional"
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
                    <strong>Backend note</strong>
                    Party ID is generated automatically like P-00001. Party Type, Linked Ledger, and Debit/Credit options are loaded from backend.
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
                        Save Party
                    </button>
                </div>
            </form>
        </div>
    </aside>
</div>
@endsection
