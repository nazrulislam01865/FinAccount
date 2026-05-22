@extends('layouts.app')

@section('title', 'Users & Roles | Accounting System')

@section('content')
@php
    $currentUser = auth()->user();
    $assignableLookup = collect($assignableRoleIds ?? [])->mapWithKeys(fn ($id) => [(int) $id => true]);
@endphp

<style>
    .role-list {
        display: grid;
        grid-template-columns: 1fr;
        gap: 10px;
        max-height: 430px;
        overflow-y: auto;
        padding: 2px 4px 2px 0;
    }

    .role-option {
        display: grid;
        grid-template-columns: 24px minmax(0, 1fr) auto;
        gap: 12px;
        align-items: start;
        width: 100%;
        min-width: 0;
        border: 1px solid var(--line);
        border-radius: 14px;
        padding: 13px 14px;
        background: #fff;
        cursor: pointer;
        transition: border-color .16s ease, background .16s ease, box-shadow .16s ease;
    }

    .role-option:hover {
        border-color: #bfdbfe;
        background: #f8fbff;
    }

    .role-option input {
        width: 20px;
        height: 20px;
        min-height: 20px;
        margin: 2px 0 0;
        padding: 0;
        cursor: pointer;
        accent-color: var(--primary);
    }

    .role-option-body {
        display: grid;
        gap: 4px;
        min-width: 0;
    }

    .role-option-title {
        color: #1d2939;
        font-size: 14px;
        font-weight: 850;
        line-height: 1.25;
        overflow-wrap: anywhere;
    }

    .role-option-desc {
        margin: 0;
        color: var(--muted);
        font-size: 12px;
        line-height: 1.45;
        overflow-wrap: anywhere;
    }

    .role-option-level {
        justify-self: end;
        align-self: start;
    }

    .role-option.is-selected {
        border-color: var(--primary);
        background: var(--primary-soft);
        box-shadow: inset 0 0 0 1px #bfdbfe;
    }

    .role-option.is-disabled {
        cursor: not-allowed;
        opacity: .62;
        background: #f9fafb;
    }

    .role-option.is-disabled input {
        cursor: not-allowed;
    }

    .role-summary { display:flex; flex-wrap:wrap; gap:8px; margin-top:8px; }
    .matrix-scroll {
        max-height: none;
        overflow-x: auto;
        overflow-y: hidden;
        padding-bottom: 10px;
        overscroll-behavior-x: contain;
    }
    .matrix-scroll table { min-width: 1280px; }
    .matrix-scroll table th, .matrix-scroll table td { white-space:nowrap; vertical-align:middle; }
    .matrix-scroll table th:first-child, .matrix-scroll table td:first-child {
        position: sticky;
        left: 0;
        z-index: 2;
        background: #fff;
        box-shadow: 1px 0 0 var(--line);
    }
    .matrix-scroll table thead th:first-child { z-index: 3; }
    .permission-cell { min-width: 240px; white-space:normal !important; }
    .permission-module { color:var(--muted); font-size:11px; font-weight:800; text-transform:uppercase; letter-spacing:.04em; }
    .permission-key { color:var(--muted); font-size:11px; margin-top:4px; font-family:ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace; }
    .permission-decision-cell { min-width: 148px; }
    .access-select {
        min-width: 132px;
        height: 38px;
        min-height: 38px;
        border-width: 1.5px;
        border-radius: 999px;
        padding: 7px 34px 7px 14px;
        font-size: 12px;
        font-weight: 900;
        cursor: pointer;
        appearance: none;
        background-image: linear-gradient(45deg, transparent 50%, currentColor 50%), linear-gradient(135deg, currentColor 50%, transparent 50%);
        background-position: calc(100% - 18px) 16px, calc(100% - 13px) 16px;
        background-size: 5px 5px, 5px 5px;
        background-repeat: no-repeat;
    }
    .access-select:disabled { cursor: not-allowed; opacity: 1; }
    .access-select.is-allowed { border-color:#86efac; background-color:#f0fdf4; color:#166534; }
    .access-select.is-denied { border-color:#fca5a5; background-color:#fff1f2; color:#991b1b; }
    .access-select.is-locked { border-color:#bfdbfe; background-color:#eff6ff; color:#1d4ed8; }
    .access-select:focus { border-color: currentColor; box-shadow: 0 0 0 4px rgba(37,99,235,.12); }
    .matrix-actions { display:flex; flex-wrap:wrap; gap:10px; align-items:center; justify-content:space-between; padding:14px 20px; border-top:1px solid var(--line); }
    .blocked-note { color:#b42318; font-size:12px; font-weight:750; }

    @media (max-width: 520px) {
        .role-option {
            grid-template-columns: 24px minmax(0, 1fr);
        }

        .role-option-level {
            grid-column: 2;
            justify-self: start;
            margin-top: 2px;
        }
    }
</style>

<div class="page-title">
    <div>
        <h2>Users & Roles</h2>
        <p>Role-matrix controlled user creation, role assignment, and feature access authorization.</p>
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

            @if($canManageUsers)
                <button class="btn-primary" type="button" id="addUserBtn">+ Add User</button>
            @else
                <button class="btn-ghost" type="button" disabled>View Only</button>
            @endif
        </div>

        <div class="card table-card">
            <table id="usersTable">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Email</th>
                        <th>Roles</th>
                        <th>Hierarchy</th>
                        <th>Status</th>
                        <th style="text-align:right">Actions</th>
                    </tr>
                </thead>

                <tbody>
                    @forelse($users as $user)
                        @php
                            $roleNames = $user->roles->pluck('name')->join(', ');
                            $roleIds = $user->roles->pluck('id')->map(fn ($id) => (int) $id)->values();
                            $canManageThisUser = $canManageUsers && $currentUser?->canManageUser($user);
                            $highestRoleLevel = $user->roles->min('level') ?? 999;
                        @endphp

                        <tr
                            data-id="{{ $user->id }}"
                            data-name="{{ e($user->name) }}"
                            data-email="{{ $user->email }}"
                            data-role="{{ $roleNames }}"
                            data-role-ids='@json($roleIds)'
                            data-status="{{ $user->status ?? 'Active' }}"
                            data-update-url="{{ url('/api/users/' . $user->id) }}"
                            data-can-manage="{{ $canManageThisUser ? '1' : '0' }}"
                        >
                            <td class="strong">{{ $user->name }}</td>
                            <td>{{ $user->email }}</td>
                            <td>
                                @if($roleNames)
                                    <div class="role-summary">
                                        @foreach($user->roles->sortBy('level') as $role)
                                            <span class="badge {{ $role->isSuperAdmin() ? 'badge-danger' : 'badge-primary' }}">{{ $role->name }}</span>
                                        @endforeach
                                    </div>
                                @else
                                    <span class="badge badge-neutral">No Role</span>
                                @endif
                            </td>
                            <td>
                                <span class="badge badge-neutral">Level {{ $highestRoleLevel }}</span>
                            </td>
                            <td>
                                <span class="badge {{ ($user->status ?? 'Active') === 'Active' ? 'badge-success' : 'badge-neutral' }}">
                                    {{ $user->status ?? 'Active' }}
                                </span>
                            </td>
                            <td>
                                <div class="action-cell">
                                    @if($canManageThisUser)
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
                                    @else
                                        <span class="blocked-note">Protected</span>
                                    @endif
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
                <div>
                    <h3>Role Access Matrix</h3>
                    <p class="muted" style="margin:5px 0 0;font-size:13px">Column-wise roles and row-wise tasks are now connected to the real backend permission table. Authorized users can grant or remove access role-by-role from this matrix.</p>
                </div>

                @if($canManageRolePermissions)
                    <span class="badge badge-success">Matrix Editable</span>
                @else
                    <span class="badge badge-neutral">View Only</span>
                @endif
            </div>

            <form
                id="rolePermissionMatrixForm"
                method="POST"
                action="{{ route('api.roles.permissions.update') }}"
                data-frontend-form
                data-action="{{ route('api.roles.permissions.update') }}"
                data-success="Role access matrix updated successfully."
            >
                @csrf

                <div class="matrix-scroll">
                    <table>
                        <thead>
                            <tr>
                                <th class="permission-cell">Task / Permission</th>
                                @foreach($roles as $role)
                                    <th>
                                        <div class="strong">{{ $role->name }}</div>
                                        <div class="permission-key">Level {{ $role->level }}</div>
                                    </th>
                                @endforeach
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($permissionRows as $permission)
                                <tr>
                                    <td class="permission-cell">
                                        <div class="permission-module">{{ $permission['module'] }}</div>
                                        <div class="strong">{{ $permission['label'] }}</div>
                                        <div class="permission-key">{{ $permission['name'] }}</div>
                                    </td>

                                    @foreach($roles as $role)
                                        @php
                                            $hasAccess = $role->isSuperAdmin() || !empty($rolePermissionMatrix[(int) $role->id][$permission['name']]);
                                        @endphp
                                        <td class="permission-decision-cell">
                                            @if($role->isSuperAdmin())
                                                <select class="access-select is-locked" disabled title="Super Admin remains fully protected">
                                                    <option selected>Always Full</option>
                                                </select>
                                            @else
                                                <select
                                                    class="access-select {{ $hasAccess ? 'is-allowed' : 'is-denied' }}"
                                                    name="permissions[{{ $role->id }}][{{ $permission['name'] }}]"
                                                    data-access-select
                                                    {{ $canManageRolePermissions ? '' : 'disabled' }}
                                                >
                                                    <option value="1" {{ $hasAccess ? 'selected' : '' }}>Allow Access</option>
                                                    <option value="0" {{ !$hasAccess ? 'selected' : '' }}>Block Access</option>
                                                </select>
                                            @endif
                                        </td>
                                    @endforeach
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="matrix-actions">
                    <div class="hint">
                        Access changes affect every user assigned to that role. To remove access from one person, edit that user and remove/replace the role.
                    </div>

                    @if($canManageRolePermissions)
                        <button class="btn-primary" type="submit">Save Role Access</button>
                    @else
                        <button class="btn-ghost" type="button" disabled>Requires Role Permission</button>
                    @endif
                </div>
            </form>
        </div>
    </div>

    <aside class="right-stack">
        <div class="card form-panel">
            <div class="panel-head">
                <h3 id="userFormTitle">Create User</h3>
                <span class="muted">Matrix controlled</span>
            </div>

            @if($canManageUsers)
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
                        <input name="password" type="password" id="passwordInput" minlength="8" required>
                        <div class="hint" id="passwordHint">Required for new users. Leave blank while editing to keep the old password.</div>
                    </div>

                    <div>
                        <label>Confirm Password <span class="required" id="passwordConfirmRequired">*</span></label>
                        <input name="password_confirmation" type="password" id="passwordConfirmInput" minlength="8" required>
                    </div>

                    <div>
                        <label>Status <span class="required">*</span></label>
                        <select name="status" required>
                            <option value="Active">Active</option>
                            <option value="Inactive">Inactive</option>
                        </select>
                    </div>

                    <div>
                        <label>Assign Role <span class="required">*</span></label>
                        <div class="role-list" id="roleGrid">
                            @foreach($roles as $role)
                                @php
                                    $canAssign = isset($assignableLookup[(int) $role->id]);
                                @endphp
                                <label class="role-option {{ $canAssign ? '' : 'is-disabled' }}" data-role-option>
                                    <input
                                        type="checkbox"
                                        name="role_ids[]"
                                        value="{{ $role->id }}"
                                        data-role-checkbox
                                        data-role-name="{{ $role->name }}"
                                        data-role-level="{{ $role->level }}"
                                        {{ $canAssign ? '' : 'disabled' }}
                                    >

                                    <span class="role-option-body">
                                        <span class="role-option-title">{{ $role->name }}</span>
                                        <span class="role-option-desc">{{ $role->description }}</span>
                                        @if(!$canAssign)
                                            <span class="blocked-note">Cannot assign from your current role level.</span>
                                        @endif
                                    </span>

                                    <span class="badge role-option-level {{ $role->isSuperAdmin() ? 'badge-danger' : 'badge-neutral' }}">Level {{ $role->level }}</span>
                                </label>
                            @endforeach
                        </div>
                        <div class="hint" id="rolePreview">Select at least one role.</div>
                    </div>

                    <div class="hint-box">
                        <strong>Hierarchy rule</strong>
                        Users with Manage Users permission can manage lower-level users. Super Admin remains protected so the system owner cannot be locked out.
                    </div>

                    <div class="form-actions">
                        <button type="button" class="btn-ghost" id="cancelUserBtn">Cancel</button>
                        <button type="submit" class="btn-primary">Save User</button>
                    </div>
                </form>
            @else
                <div class="hint-box">
                    <strong>View-only access</strong>
                    Your role can view this screen but cannot create users, change roles, or update permissions.
                </div>
            @endif
        </div>

        <div class="card info-card">
            <h3>Access Control Rule</h3>
            <p>Role matrix changes are saved to the permission_role table and enforced by middleware, route checks, and read-only UI locks across the system.</p>
        </div>
    </aside>
</div>


<script>
document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('[data-access-select]').forEach((select) => {
        const syncAccessState = () => {
            select.classList.toggle('is-allowed', select.value === '1');
            select.classList.toggle('is-denied', select.value !== '1');
        };

        select.addEventListener('change', syncAccessState);
        syncAccessState();
    });
});
</script>

@if($canManageUsers)
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
    const passwordConfirmInput = document.getElementById('passwordConfirmInput');
    const passwordRequired = document.getElementById('passwordRequired');
    const passwordConfirmRequired = document.getElementById('passwordConfirmRequired');
    const rolePreview = document.getElementById('rolePreview');

    const name = form.querySelector('[name="name"]');
    const email = form.querySelector('[name="email"]');
    const status = form.querySelector('[name="status"]');
    const roleCheckboxes = Array.from(form.querySelectorAll('[data-role-checkbox]'));

    function showToast(message) {
        if (window.AccountingUI?.showToast) {
            window.AccountingUI.showToast(message);
            return;
        }

        alert(message);
    }

    function updateRolePreview() {
        const selected = roleCheckboxes
            .filter((checkbox) => checkbox.checked)
            .map((checkbox) => `${checkbox.dataset.roleName} (L${checkbox.dataset.roleLevel})`);

        roleCheckboxes.forEach((checkbox) => {
            checkbox.closest('[data-role-option]')?.classList.toggle('is-selected', checkbox.checked);
        });

        rolePreview.textContent = selected.length ? `Selected: ${selected.join(', ')}` : 'Select at least one role.';
    }

    function clearRoles() {
        roleCheckboxes.forEach((checkbox) => {
            checkbox.checked = false;
        });
        updateRolePreview();
    }

    function setRoles(roleIds) {
        const selectedIds = Array.isArray(roleIds) ? roleIds.map((id) => String(id)) : [];
        roleCheckboxes.forEach((checkbox) => {
            checkbox.checked = selectedIds.includes(String(checkbox.value)) && !checkbox.disabled;
        });
        updateRolePreview();
    }

    function resetForm() {
        form.reset();
        clearRoles();
        form.dataset.action = form.dataset.storeUrl;
        methodInput.value = 'POST';
        title.textContent = 'Create User';
        passwordInput.required = true;
        passwordConfirmInput.required = true;
        passwordRequired.style.display = '';
        passwordConfirmRequired.style.display = '';
        name.focus();
    }

    function loadForEdit(row) {
        if (!row || row.dataset.canManage !== '1') {
            showToast('This user is protected by role hierarchy rules.');
            return;
        }

        form.dataset.action = row.dataset.updateUrl;
        methodInput.value = 'PUT';
        title.textContent = 'Edit User';

        name.value = row.dataset.name || '';
        email.value = row.dataset.email || '';
        status.value = row.dataset.status || 'Active';
        passwordInput.value = '';
        passwordConfirmInput.value = '';
        passwordInput.required = false;
        passwordConfirmInput.required = false;
        passwordRequired.style.display = 'none';
        passwordConfirmRequired.style.display = 'none';

        try {
            setRoles(JSON.parse(row.dataset.roleIds || '[]'));
        } catch (error) {
            clearRoles();
        }

        form.scrollIntoView({ behavior: 'smooth', block: 'center' });
        showToast('User loaded for editing.');
    }

    form.addEventListener('submit', (event) => {
        const selectedRoleCount = roleCheckboxes.filter((checkbox) => checkbox.checked).length;

        if (selectedRoleCount < 1) {
            event.preventDefault();
            event.stopImmediatePropagation();
            showToast('Please select at least one role.');
            return;
        }

        if (passwordInput.value || passwordConfirmInput.value) {
            if (passwordInput.value !== passwordConfirmInput.value) {
                event.preventDefault();
                event.stopImmediatePropagation();
                showToast('Password and confirmation do not match.');
            }
        }
    }, true);

    roleCheckboxes.forEach((checkbox) => checkbox.addEventListener('change', updateRolePreview));


    document.querySelectorAll('#usersTable .edit-btn').forEach((button) => {
        button.addEventListener('click', () => loadForEdit(button.closest('tr')));
    });

    addButton?.addEventListener('click', () => {
        resetForm();
        showToast('Ready to add a user.');
    });

    cancelButton?.addEventListener('click', () => {
        resetForm();
        showToast('Form cleared.');
    });

    updateRolePreview();
});
</script>
@endif
@endsection
