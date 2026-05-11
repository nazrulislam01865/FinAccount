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
            <div class="field search-field"><span>⌕</span><input placeholder="Search heads..." data-filter-key="text"></div>
            <div><label>Nature</label><select data-filter-key="nature"><option>All Categories</option><option>Payment</option><option>Receipt</option><option>Due</option><option>Advance</option><option>Adjustment</option></select></div>
            <div><label>Party Type</label><select data-filter-key="party"><option>All Party Types</option><option>Employee</option><option>Supplier</option><option>Customer</option><option>Driver</option><option>Landlord</option></select></div>
            <div><label>Status</label><select data-filter-key="status"><option>All Status</option><option>Active</option><option>Inactive</option></select></div>
            <button class="btn-primary" type="button" data-toast="Ready to add a transaction head.">+ Add Transaction Head</button>
        </div>

        <div class="card table-card">
            <table id="headTable">
                <thead><tr><th>Head Name</th><th>Nature</th><th>Default Party Type</th><th>Requires Party</th><th>Requires Reference</th><th>Default Settlement Types</th><th>Status</th><th style="text-align:right">Actions</th></tr></thead>
                <tbody>
                    <tr data-nature="Payment" data-party="Employee" data-status="Active"><td class="strong">Salary Payment</td><td><span class="badge badge-danger">Payment</span></td><td>Employee</td><td>✓</td><td>×</td><td>Cash, Bank</td><td><span class="badge badge-success">Active</span></td><td><div class="action-cell"><button class="icon-btn">✎</button><button class="icon-btn">⋮</button></div></td></tr>
                    <tr data-nature="Payment" data-party="Supplier" data-status="Active"><td class="strong">Fuel Expense</td><td><span class="badge badge-danger">Payment</span></td><td>Supplier</td><td>✓</td><td>×</td><td>Cash, Bank, Due</td><td><span class="badge badge-success">Active</span></td><td><div class="action-cell"><button class="icon-btn">✎</button><button class="icon-btn">⋮</button></div></td></tr>
                    <tr data-nature="Receipt" data-party="Customer" data-status="Active"><td class="strong">Rent Income</td><td><span class="badge badge-success">Receipt</span></td><td>Customer</td><td>✓</td><td>×</td><td>Cash, Bank</td><td><span class="badge badge-success">Active</span></td><td><div class="action-cell"><button class="icon-btn">✎</button><button class="icon-btn">⋮</button></div></td></tr>
                    <tr data-nature="Due" data-party="Employee" data-status="Active"><td class="strong">Salary Due Entry</td><td><span class="badge badge-warning">Due</span></td><td>Employee</td><td>✓</td><td>×</td><td>Due</td><td><span class="badge badge-success">Active</span></td><td><div class="action-cell"><button class="icon-btn">✎</button><button class="icon-btn">⋮</button></div></td></tr>
                    <tr data-nature="Advance" data-party="Supplier" data-status="Active"><td class="strong">Advance Paid</td><td><span class="badge badge-purple">Advance</span></td><td>Supplier</td><td>✓</td><td>×</td><td>Advance Paid, Cash, Bank</td><td><span class="badge badge-success">Active</span></td><td><div class="action-cell"><button class="icon-btn">✎</button><button class="icon-btn">⋮</button></div></td></tr>
                </tbody>
            </table>
            <div class="table-footer"><span id="resultCount">Showing 5 of 5 entries</span><div class="pagination"><button class="page-btn">‹</button><button class="page-btn active">1</button><button class="page-btn">›</button></div></div>
        </div>
    </div>

    <aside class="right-stack">
        @include('partials.setup-progress', ['current' => 5])
        <div class="card form-panel">
            <div class="panel-head"><h3>Create / Edit Transaction Head</h3><span class="muted">×</span></div>
            <form class="form-grid" data-frontend-form data-action="/api/transaction-heads" data-success="Transaction head saved successfully.">
                <div><label>Transaction Head Name <span class="required">*</span></label><input name="name" placeholder="Enter head name" required></div>
                <div><label>Nature <span class="required">*</span></label><select name="nature" required><option>Payment</option><option>Receipt</option><option>Due</option><option>Advance</option><option>Adjustment</option></select></div>
                <div><label>Default Party Type</label><select name="default_party_type_id" data-dropdown="/api/dropdowns/party-types" data-placeholder="Select Party Type"></select></div>
                <div class="two-col">
                    <div class="switch-row"><span class="switch-label">Requires Party</span><input type="hidden" id="requiresParty" name="requires_party" value="1"><div class="switch on" data-input="requiresParty"></div></div>
                    <div class="switch-row"><span class="switch-label">Requires Reference</span><input type="hidden" id="requiresReference" name="requires_reference" value="0"><div class="switch" data-input="requiresReference"></div></div>
                </div>
                <div><label>Description</label><textarea name="description" placeholder="Enter description optional"></textarea></div>
                <div><label>Status <span class="required">*</span></label><select name="status" required><option>Active</option><option>Inactive</option></select></div>
                <div>
                    <label>Allowed Settlement Types <span class="required">*</span></label>
                    <input type="hidden" id="settlementTypes" name="allowed_settlement_types" value="[]">
                    <div class="multi-select" data-multi-select data-input="settlementTypes">
                        <span class="select-chip selected" data-value="Cash">Cash</span>
                        <span class="select-chip selected" data-value="Bank">Bank</span>
                        <span class="select-chip" data-value="Due">Due</span>
                        <span class="select-chip" data-value="Advance Paid">Advance Paid</span>
                        <span class="select-chip" data-value="Advance Received">Advance Received</span>
                        <span class="select-chip" data-value="Advance Adjustment">Advance Adjustment</span>
                    </div>
                </div>
                <div class="hint-box"><strong>Backend note</strong>Allowed settlement types may be stored in a pivot table.</div>
                <div class="form-actions"><button type="button" class="btn-ghost" data-toast="Form cleared.">Cancel</button><button type="submit" class="btn-primary">Save Head</button></div>
            </form>
        </div>
    </aside>
</div>
@endsection
