@extends('layouts.app')

@section('title', 'Users & Roles | Accounting System')

@section('content')
<div class="page-title">
    <div>
        <span class="page-label">Users & Roles</span>
        <h2>Users & Roles</h2>
        <p>Create users, assign roles, and prepare Sprint 1 permission foundation.</p>
    </div>
</div>

<div class="layout">
    <div class="left-stack">
        <div class="card toolbar" data-table-filter="#usersTable" data-count-target="#resultCount">
            <div class="field search-field">
                <span>⌕</span>
                <input placeholder="Search users..." data-filter-key="text">
            </div>

            <div>
                <label>Role</label>
                <select data-filter-key="role">
                    <option value="">All Roles</option>
                    @foreach($roles as $role)
                        <option value="{{ $role->name }}">{{ $role->name }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label>Status</label>
                <select data-filter-key="status">
                    <option value="">All Status</option>
                    <option value="Active">Active</option>
                    <option value="Inactive">Inactive</option>
                </select>
            </div>

            <button class="btn-primary" type="button" id="addUserBtn">+ Add User</button>
        </div>

        <div class="card table-card">
            <table id="usersTable">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Email</th>
                        <th>Role</th>
                        <th>Status</th>
                        <th>Last Login</th>
                        <th style="text-align:right">Actions</th>
                    </tr>
                </thead>

                <tbody>
                    @forelse($users as $user)
                        @php
                            $primaryRole = $user->roles->first()?->name;
                            $roleNames = $user->roles->pluck('name')->join(', ');
                        @endphp

                        <tr
                            data-id="{{ $user->id }}"
                            data-name="{{ e($user->name) }}"
                            data-email="{{ $user->email }}"
                            data-role="{{ $primaryRole }}"
                            data-role-ids='{{ $user->roles->pluck('id')->values()->toJson() }}'
                            data-status="{{ $user->status ?? 'Active' }}"
                            data-update-url="{{ url('/api/users/' . $user->id) }}"
                        >
                            <td class="strong">{{ $user->name }}</td>
                            <td>{{ $user->email }}</td>
                            <td>
                                @if($roleNames)
                                    <span class="badge badge-primary">{{ $roleNames }}</span>
                                @else
                                    <span class="badge badge-neutral">No Role</span>
                                @endif
                            </td>
                            <td>
                                <span class="badge {{ ($user->status ?? 'Active') === 'Active' ? 'badge-success' : 'badge-neutral' }}">
                                    {{ $user->status ?? 'Active' }}
                                </span>
                            </td>
                            <td class="muted">Never</td>
                            <td>
                                <div class="action-cell">
                                    <button class="icon-btn edit-btn" type="button" title="Edit">✎</button>

                                    <form
                                        method="POST"
                                        data-delete-form
                                        action="{{ url('/settings/users/' . $user->id) }}"
                                        onsubmit="return confirm('Delete this user?')"
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
                            <td colspan="6" class="muted" style="text-align:center;padding:24px">
                                No users found. Add your first user using the form on the right.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>

            <div class="table-footer">
                <span id="resultCount">Showing {{ $users->count() }} of {{ $users->count() }} entries</span>

                <div class="pagination">
                    <button class="page-btn" type="button">‹</button>
                    <button class="page-btn active" type="button">1</button>
                    <button class="page-btn" type="button">›</button>
                </div>
            </div>
        </div>

        <div class="card table-card">
            <div class="panel-head" style="padding:18px 20px;margin:0;border-bottom:1px solid var(--line)">
                <h3>Permission Matrix</h3>
            </div>

            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>Feature</th>
                            <th>Admin</th>
                            <th>Accountant</th>
                            <th>Data Entry</th>
                            <th>Manager</th>
                        </tr>
                    </thead>
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
            <div class="panel-head">
                <h3 id="userFormTitle">Create / Edit User</h3>
                <span class="muted">×</span>
            </div>

            <form
                class="form-grid"
                id="userForm"
                data-frontend-form
                data-action="{{ route('api.users.store') }}"
                data-store-url="{{ route('api.users.store') }}"
                data-success="User saved successfully."
            >
                @csrf

                <input type="hidden" name="_method" id="userFormMethod" value="POST">

                <div>
                    <label>Name <span class="required">*</span></label>
                    <input name="name" required>
                </div>

                <div>
                    <label>Email <span class="required">*</span></label>
                    <input name="email" type="email" required>
                </div>

                <div>
                    <label>Password <span class="required" id="passwordRequired">*</span></label>
                    <input name="password" type="password" id="passwordInput" required>
                    <div class="hint" id="passwordHint">Required when creating a user. Leave blank while editing to keep the current password.</div>
                </div>

                <div>
                    <label>Role <span class="required">*</span></label>
                    <select name="role" required>
                        @foreach($roles as $role)
                            <option value="{{ $role->name }}">{{ $role->name }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label>Status <span class="required">*</span></label>
                    <select name="status" required>
                        <option value="Active">Active</option>
                        <option value="Inactive">Inactive</option>
                    </select>
                </div>

                <div class="hint-box">
                    <strong>Sprint 1</strong>
                    This screen prepares user and role foundation. Backend permission enforcement will be connected after routes and policies are ready.
                </div>

                <div class="form-actions">
                    <button type="button" class="btn-ghost" id="cancelUserBtn">Cancel</button>
                    <button type="submit" class="btn-primary">Save User</button>
                </div>
            </form>
        </div>

        <div class="card info-card">
            <h3>Minimum Roles</h3>
            <p>Admin, Accountant, Data Entry, and Manager are required for Sprint 1 foundation.</p>
        </div>
    </aside>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const form = document.getElementById('userForm');

    if (!form) {
        return;
    }

    const title = document.getElementById('userFormTitle');
    const methodInput = document.getElementById('userFormMethod');
    const addButton = document.getElementById('addUserBtn');
    const cancelButton = document.getElementById('cancelUserBtn');
    const passwordInput = document.getElementById('passwordInput');
    const passwordRequired = document.getElementById('passwordRequired');

    const name = form.querySelector('[name="name"]');
    const email = form.querySelector('[name="email"]');
    const role = form.querySelector('[name="role"]');
    const status = form.querySelector('[name="status"]');

    function showToast(message) {
        if (window.AccountingUI?.showToast) {
            window.AccountingUI.showToast(message);
            return;
        }

        alert(message);
    }

    function resetForm() {
        form.reset();
        form.dataset.action = form.dataset.storeUrl;
        methodInput.value = 'POST';
        title.textContent = 'Create / Edit User';
        passwordInput.required = true;
        passwordRequired.style.display = '';
        name.focus();
    }

    function loadForEdit(row) {
        form.dataset.action = row.dataset.updateUrl;
        methodInput.value = 'PUT';
        title.textContent = 'Edit User';

        name.value = row.dataset.name || '';
        email.value = row.dataset.email || '';
        role.value = row.dataset.role || '';
        status.value = row.dataset.status || 'Active';
        passwordInput.value = '';
        passwordInput.required = false;
        passwordRequired.style.display = 'none';

        form.scrollIntoView({ behavior: 'smooth', block: 'center' });
        showToast('User loaded for editing.');
    }

    document.querySelectorAll('#usersTable .edit-btn').forEach((button) => {
        button.addEventListener('click', () => loadForEdit(button.closest('tr')));
    });

    addButton.addEventListener('click', () => {
        resetForm();
        showToast('Ready to add a user.');
    });

    cancelButton.addEventListener('click', () => {
        resetForm();
        showToast('Form cleared.');
    });
});
</script>
@endsection
