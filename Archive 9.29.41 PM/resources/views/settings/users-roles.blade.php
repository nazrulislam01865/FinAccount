@extends('layouts.app')
@section('title', 'Users & Roles | Accounting System')
@section('content')
<div class="page-title">
    <div>
        <h2>Users & Roles</h2>
        <p>Create users, assign roles, and prepare Sprint 1 permission foundation.</p>
    </div>
</div>

<div class="layout">
    <div class="left-stack">
        <div class="card toolbar" data-table-filter="#usersTable" data-count-target="#resultCount">
            <div class="field search-field"><span>⌕</span><input placeholder="Search users..." data-filter-key="text"></div>
            <div><label>Role</label><select data-filter-key="role"><option>All Roles</option><option>Admin</option><option>Accountant</option><option>Data Entry</option><option>Manager</option></select></div>
            <div><label>Status</label><select data-filter-key="status"><option>All Status</option><option>Active</option><option>Inactive</option></select></div>
            <button class="btn-primary" type="button" data-toast="Ready to add a user.">+ Add User</button>
        </div>

        <div class="card table-card">
            <table id="usersTable">
                <thead><tr><th>Name</th><th>Email</th><th>Role</th><th>Status</th><th>Last Login</th><th style="text-align:right">Actions</th></tr></thead>
                <tbody>
                    <tr data-role="Admin" data-status="Active"><td class="strong">Admin User</td><td>admin@example.com</td><td><span class="badge badge-primary">Admin</span></td><td><span class="badge badge-success">Active</span></td><td class="muted">Today</td><td><div class="action-cell"><button class="icon-btn">✎</button><button class="icon-btn">⋮</button></div></td></tr>
                    <tr data-role="Accountant" data-status="Active"><td class="strong">Accountant</td><td>accountant@example.com</td><td><span class="badge badge-success">Accountant</span></td><td><span class="badge badge-success">Active</span></td><td class="muted">Yesterday</td><td><div class="action-cell"><button class="icon-btn">✎</button><button class="icon-btn">⋮</button></div></td></tr>
                    <tr data-role="Data Entry" data-status="Active"><td class="strong">Data Entry User</td><td>entry@example.com</td><td><span class="badge badge-warning">Data Entry</span></td><td><span class="badge badge-success">Active</span></td><td class="muted">Never</td><td><div class="action-cell"><button class="icon-btn">✎</button><button class="icon-btn">⋮</button></div></td></tr>
                    <tr data-role="Manager" data-status="Active"><td class="strong">Manager</td><td>manager@example.com</td><td><span class="badge badge-purple">Manager</span></td><td><span class="badge badge-success">Active</span></td><td class="muted">Never</td><td><div class="action-cell"><button class="icon-btn">✎</button><button class="icon-btn">⋮</button></div></td></tr>
                </tbody>
            </table>
            <div class="table-footer"><span id="resultCount">Showing 4 of 4 entries</span><div class="pagination"><button class="page-btn">‹</button><button class="page-btn active">1</button><button class="page-btn">›</button></div></div>
        </div>

        <div class="card table-card">
            <div class="panel-head" style="padding:18px 20px;margin:0;border-bottom:1px solid var(--line)"><h3>Permission Matrix</h3></div>
            <div class="table-wrap">
                <table>
                    <thead><tr><th>Feature</th><th>Admin</th><th>Accountant</th><th>Data Entry</th><th>Manager</th></tr></thead>
                    <tbody>
                        <tr><td>Company Setup</td><td>Manage</td><td>No</td><td>No</td><td>View</td></tr>
                        <tr><td>Chart of Accounts</td><td>Manage</td><td>Manage</td><td>No</td><td>View</td></tr>
                        <tr><td>Cash / Bank Setup</td><td>Manage</td><td>Manage</td><td>No</td><td>View</td></tr>
                        <tr><td>Party Setup</td><td>Manage</td><td>Manage</td><td>View</td><td>View</td></tr>
                        <tr><td>Transaction Head Setup</td><td>Manage</td><td>View</td><td>No</td><td>No</td></tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <aside class="right-stack">
        <div class="card form-panel">
            <div class="panel-head"><h3>Create / Edit User</h3><span class="muted">×</span></div>
            <form class="form-grid" data-frontend-form data-action="/api/users" data-success="User saved successfully.">
                <div><label>Name <span class="required">*</span></label><input name="name" required></div>
                <div><label>Email <span class="required">*</span></label><input name="email" type="email" required></div>
                <div><label>Password <span class="required">*</span></label><input name="password" type="password" required></div>
                <div><label>Role <span class="required">*</span></label><select name="role" required><option>Admin</option><option>Accountant</option><option>Data Entry</option><option>Manager</option></select></div>
                <div><label>Status <span class="required">*</span></label><select name="status" required><option>Active</option><option>Inactive</option></select></div>
                <div class="hint-box"><strong>Sprint 1</strong>This screen prepares user and role foundation. Backend permission enforcement will be connected after routes and policies are ready.</div>
                <div class="form-actions"><button type="button" class="btn-ghost" data-toast="Form cleared.">Cancel</button><button type="submit" class="btn-primary">Save User</button></div>
            </form>
        </div>
        <div class="card info-card"><h3>Minimum Roles</h3><p>Admin, Accountant, Data Entry, and Manager are required for Sprint 1 foundation.</p></div>
    </aside>
</div>
@endsection
