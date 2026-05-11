@extends('layouts.app')

@section('title', 'Transaction Head Setup | Accounting System')

@section('content')
<div class="page-title">
    <div>
        <h2>Transaction Head Setup</h2>
        <p>Define user-friendly transaction types for daily entries.</p>
    </div>
</div>

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

            <button class="btn-primary" type="button" data-toast="Ready to add a transaction head.">
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
                            data-nature="{{ $head->nature }}"
                            data-party="{{ $head->default_party_type_id }}"
                            data-status="{{ $head->status }}"
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
                                    <button class="icon-btn" type="button" data-toast="Edit will be added later.">✎</button>
                                    <button class="icon-btn" type="button" data-toast="More actions will be added later.">⋮</button>
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
        @include('partials.setup-progress', ['current' => 5])

        <div class="card form-panel">
            <div class="panel-head">
                <h3>Create / Edit Transaction Head</h3>
                <span class="muted">×</span>
            </div>

            <form
                class="form-grid"
                data-frontend-form
                data-action="{{ route('api.transaction-heads.store') }}"
                data-success="Transaction head saved successfully."
            >
                @csrf

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
                        <div class="switch on" data-input="requiresParty"></div>
                    </div>

                    <div class="switch-row">
                        <span class="switch-label">Requires Reference</span>
                        <input type="hidden" id="requiresReference" name="requires_reference" value="0">
                        <div class="switch" data-input="requiresReference"></div>
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
                    <button type="reset" class="btn-ghost" data-toast="Form cleared.">Cancel</button>
                    <button type="submit" class="btn-primary">Save Head</button>
                </div>
            </form>
        </div>
    </aside>
</div>
@endsection
