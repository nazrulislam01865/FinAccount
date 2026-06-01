<?php $__env->startSection('title', 'Users & Roles | HisebGhor'); ?>

<?php $__env->startSection('content'); ?>
<?php
    $currentUser = auth()->user();
    $assignableLookup = collect($assignableRoleIds ?? [])->mapWithKeys(fn ($id) => [(int) $id => true]);
    $editableMatrixUserCount = collect($users ?? [])->filter(fn ($matrixUser) => ($canManageUserPermissions ?? false) && $currentUser?->canManageUser($matrixUser) && !$matrixUser->hasFixedFullAccessRole())->count();
?>

<style>
    .users-role-page {
        display: grid;
        gap: 18px;
    }

    .users-role-grid {
        display: grid;
        grid-template-columns: minmax(0, 1.55fr) minmax(340px, .9fr);
        gap: 18px;
        align-items: start;
    }

    .users-table-wrap {
        overflow-x: auto;
        padding-bottom: 8px;
    }

    #usersTable {
        min-width: 980px;
    }

    .role-chip-list {
        display: flex;
        flex-wrap: wrap;
        gap: 6px;
    }

    .role-select,
    .inline-role-select {
        width: 100%;
        min-height: 46px;
        border: 1px solid var(--line);
        border-radius: 14px;
        padding: 10px 42px 10px 14px;
        background-color: #fff;
        color: #1d2939;
        font-weight: 800;
        appearance: none;
        background-image: linear-gradient(45deg, transparent 50%, currentColor 50%), linear-gradient(135deg, currentColor 50%, transparent 50%);
        background-position: calc(100% - 21px) 20px, calc(100% - 15px) 20px;
        background-size: 6px 6px, 6px 6px;
        background-repeat: no-repeat;
    }

    .role-select:focus,
    .inline-role-select:focus {
        border-color: var(--primary);
        box-shadow: 0 0 0 4px rgba(37, 99, 235, .12);
        outline: none;
    }

    .inline-role-form {
        display: grid;
        gap: 6px;
        min-width: 190px;
    }

    .inline-role-select {
        min-height: 40px;
        height: 40px;
        border-radius: 999px;
        padding-top: 8px;
        padding-bottom: 8px;
        font-size: 12px;
        background-position: calc(100% - 19px) 17px, calc(100% - 13px) 17px;
    }

    .inline-role-select:disabled {
        opacity: .66;
        cursor: not-allowed;
        background-color: #f8fafc;
    }

    .role-help-card {
        display: grid;
        gap: 10px;
    }

    .role-help-row {
        display: grid;
        grid-template-columns: auto 1fr;
        gap: 10px;
        align-items: start;
        padding: 10px 0;
        border-bottom: 1px solid var(--line);
    }

    .role-help-row:last-child {
        border-bottom: 0;
    }

    .role-help-dot {
        width: 28px;
        height: 28px;
        border-radius: 999px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        background: var(--primary-soft);
        color: var(--primary);
        font-weight: 900;
        font-size: 12px;
    }

    .blocked-note {
        color: #b42318;
        font-size: 12px;
        font-weight: 750;
    }

    .mini-muted {
        margin-top: 3px;
        color: var(--muted);
        font-size: 11px;
        font-weight: 650;
    }

    .dynamic-note {
        display: flex;
        flex-wrap: wrap;
        gap: 8px;
        align-items: center;
        color: var(--muted);
        font-size: 13px;
    }

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

    @media (max-width: 1100px) {
        .users-role-grid {
            grid-template-columns: 1fr;
        }
    }
</style>

<div class="page-title">
    <div>
        <h2>Users & Roles</h2>
        <p>Create users with dropdown role assignment, then control access per created user through the allow/block matrix.</p>
    </div>
</div>

<div class="users-role-page">
    <div class="users-role-grid">
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
                        <?php $__currentLoopData = $roles; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $role): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                            <option value="<?php echo e($role->name); ?>"><?php echo e($role->name); ?></option>
                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
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

                <?php if($canManageUsers): ?>
                    <button class="btn-primary" type="button" id="addUserBtn">+ Add User</button>
                <?php else: ?>
                    <button class="btn-ghost" type="button" disabled>View Only</button>
                <?php endif; ?>
            </div>

            <div class="card table-card">
                <div class="panel-head" style="padding:18px 20px;margin:0;border-bottom:1px solid var(--line)">
                    <div>
                        <h3>User Role Table</h3>
                        <p class="muted" style="margin:5px 0 0;font-size:13px">Each created user appears here. Authorized Admin or Super Admin users can update the user's role directly from the table.</p>
                    </div>
                    <span class="badge badge-primary">Dynamic Users</span>
                </div>

                <div class="users-table-wrap">
                    <table id="usersTable">
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Email</th>
                                <th>Assigned Role</th>
                                <th>Hierarchy</th>
                                <th>Status</th>
                                <th style="text-align:right">Actions</th>
                            </tr>
                        </thead>

                        <tbody>
                            <?php $__empty_1 = true; $__currentLoopData = $users; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $user): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
                                <?php
                                    $sortedRoles = $user->roles->sortBy('level')->values();
                                    $roleNames = $sortedRoles->pluck('name')->join(', ');
                                    $roleIds = $sortedRoles->pluck('id')->map(fn ($id) => (int) $id)->values();
                                    $primaryRoleId = (int) ($roleIds->first() ?? 0);
                                    $canManageThisUser = $canManageUsers && $currentUser?->canManageUser($user);
                                    $highestRoleLevel = $sortedRoles->min('level') ?? 999;
                                ?>

                                <tr
                                    data-id="<?php echo e($user->id); ?>"
                                    data-name="<?php echo e(e($user->name)); ?>"
                                    data-email="<?php echo e($user->email); ?>"
                                    data-role="<?php echo e($roleNames); ?>"
                                    data-role-ids='<?php echo json_encode($roleIds, 15, 512) ?>'
                                    data-status="<?php echo e($user->status ?? 'Active'); ?>"
                                    data-update-url="<?php echo e(url('/api/users/' . $user->id)); ?>"
                                    data-can-manage="<?php echo e($canManageThisUser ? '1' : '0'); ?>"
                                >
                                    <td class="strong"><?php echo e($user->name); ?></td>
                                    <td><?php echo e($user->email); ?></td>
                                    <td>
                                        <?php if($canManageThisUser): ?>
                                            <div class="inline-role-form">
                                                <select class="inline-role-select" data-inline-role-select data-original-role="<?php echo e($primaryRoleId); ?>" aria-label="Change role for <?php echo e($user->name); ?>">
                                                    <option value="">Select role</option>
                                                    <?php $__currentLoopData = $roles; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $role): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                                        <?php
                                                            $canAssign = isset($assignableLookup[(int) $role->id]);
                                                        ?>
                                                        <option
                                                            value="<?php echo e($role->id); ?>"
                                                            data-role-name="<?php echo e($role->name); ?>"
                                                            data-role-level="<?php echo e($role->level); ?>"
                                                            <?php echo e($primaryRoleId === (int) $role->id ? 'selected' : ''); ?>

                                                            <?php echo e($canAssign ? '' : 'disabled'); ?>

                                                        >
                                                            <?php echo e($role->name); ?> — Level <?php echo e($role->level); ?>

                                                        </option>
                                                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                                                </select>
                                                <span class="mini-muted">Change saves instantly.</span>
                                            </div>
                                        <?php elseif($roleNames): ?>
                                            <div class="role-chip-list">
                                                <?php $__currentLoopData = $sortedRoles; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $role): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                                    <span class="badge <?php echo e($role->isSuperAdmin() ? 'badge-danger' : 'badge-primary'); ?>"><?php echo e($role->name); ?></span>
                                                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                                            </div>
                                        <?php else: ?>
                                            <span class="badge badge-neutral">No Role</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="badge badge-neutral">Level <?php echo e($highestRoleLevel); ?></span>
                                    </td>
                                    <td>
                                        <span class="badge <?php echo e(($user->status ?? 'Active') === 'Active' ? 'badge-success' : 'badge-neutral'); ?>">
                                            <?php echo e($user->status ?? 'Active'); ?>

                                        </span>
                                    </td>
                                    <td>
                                        <div class="action-cell">
                                            <?php if($canManageThisUser): ?>
                                                <button class="icon-btn edit-btn" type="button" title="Edit">✎</button>

                                                <form
                                                    method="POST"
                                                    data-delete-form
                                                    action="<?php echo e(url('/settings/users/' . $user->id)); ?>"
                                                    onsubmit="return confirm('Delete this user?')"
                                                >
                                                    <?php echo csrf_field(); ?>
                                                    <?php echo method_field('DELETE'); ?>

                                                    <button class="icon-btn delete-btn" type="submit" title="Delete">
                                                        🗑
                                                    </button>
                                                </form>
                                            <?php else: ?>
                                                <span class="blocked-note">Protected</span>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
                                <tr data-empty="true">
                                    <td colspan="6" class="muted" style="text-align:center;padding:24px">
                                        No users found. Add your first user using the form on the right.
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <div class="table-footer">
                    <span id="resultCount">Showing <?php echo e($users->count()); ?> of <?php echo e($users->count()); ?> entries</span>
                    <div class="dynamic-note">
                        <span class="badge badge-neutral">Dynamic</span>
                        <span>New users appear here after creation.</span>
                    </div>
                </div>
            </div>
        <div class="card table-card">
            <div class="panel-head" style="padding:18px 20px;margin:0;border-bottom:1px solid var(--line)">
                <div>
                    <h3>User Access Matrix</h3>
                    <p class="muted" style="margin:5px 0 0;font-size:13px">Column-wise created users and row-wise tasks are connected to the backend permission table. Authorized Admin or Super Admin users can allow or block access for each individual user.</p>
                </div>

                <?php if($canManageUserPermissions): ?>
                    <span class="badge badge-success">Matrix Editable</span>
                <?php else: ?>
                    <span class="badge badge-neutral">View Only</span>
                <?php endif; ?>
            </div>

            <form
                id="userPermissionMatrixForm"
                method="POST"
                action="<?php echo e(route('api.roles.permissions.update')); ?>"
                data-frontend-form
                data-action="<?php echo e(route('api.roles.permissions.update')); ?>"
                data-success="User access matrix updated successfully."
            >
                <?php echo csrf_field(); ?>

                <div class="matrix-scroll">
                    <table>
                        <thead>
                            <tr>
                                <th class="permission-cell">Task / Permission</th>
                                <?php $__currentLoopData = $users; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $matrixUser): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                    <?php
                                        $matrixRoles = $matrixUser->roles->sortBy('level')->values();
                                        $matrixRoleLevel = $matrixRoles->min('level') ?? 999;
                                        $isFixedFullAccessUser = $matrixUser->hasFixedFullAccessRole();
                                        $canManageMatrixUser = $canManageUserPermissions && $currentUser?->canManageUser($matrixUser) && !$isFixedFullAccessUser;
                                    ?>
                                    <th>
                                        <div class="strong"><?php echo e($matrixUser->name); ?></div>
                                        <div class="permission-key"><?php echo e($matrixUser->email); ?></div>
                                        <div class="permission-key">Level <?php echo e($matrixRoleLevel); ?></div>
                                        <?php if($isFixedFullAccessUser): ?>
                                            <span class="badge badge-primary" style="margin-top:6px">Fixed Full Access</span>
                                        <?php elseif(!$canManageMatrixUser): ?>
                                            <span class="badge badge-neutral" style="margin-top:6px">Protected</span>
                                        <?php endif; ?>
                                    </th>
                                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                            </tr>
                        </thead>
                        <tbody>
                            <?php $__currentLoopData = $permissionRows; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $permission): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                <tr>
                                    <td class="permission-cell">
                                        <div class="permission-module"><?php echo e($permission['module']); ?></div>
                                        <div class="strong"><?php echo e($permission['label']); ?></div>
                                        <div class="permission-key"><?php echo e($permission['name']); ?></div>
                                    </td>

                                    <?php $__currentLoopData = $users; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $matrixUser): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                        <?php
                                            $isFixedFullAccessUser = $matrixUser->hasFixedFullAccessRole();
                                            $canManageMatrixUser = $canManageUserPermissions && $currentUser?->canManageUser($matrixUser) && !$isFixedFullAccessUser;
                                            $hasAccess = $isFixedFullAccessUser || !empty($userPermissionMatrix[(int) $matrixUser->id][$permission['name']]);
                                        ?>
                                        <td class="permission-decision-cell">
                                            <?php if($isFixedFullAccessUser): ?>
                                                <select class="access-select is-locked" disabled title="<?php echo e($matrixUser->name); ?> remains fixed with full access">
                                                    <option selected>Always Full</option>
                                                </select>
                                            <?php else: ?>
                                                <select
                                                    class="access-select <?php echo e($hasAccess ? 'is-allowed' : 'is-denied'); ?>"
                                                    name="permissions[<?php echo e($matrixUser->id); ?>][<?php echo e($permission['name']); ?>]"
                                                    data-access-select
                                                    <?php echo e($canManageMatrixUser ? '' : 'disabled'); ?>

                                                >
                                                    <option value="1" <?php echo e($hasAccess ? 'selected' : ''); ?>>Allow Access</option>
                                                    <option value="0" <?php echo e(!$hasAccess ? 'selected' : ''); ?>>Block Access</option>
                                                </select>
                                            <?php endif; ?>
                                        </td>
                                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                                </tr>
                            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                        </tbody>
                    </table>
                </div>

                <div class="matrix-actions">
                    <div class="hint">
                        Access changes affect only the selected user column. The assigned role is still kept in the backend and is used as the default permission profile when the user is created or the role is changed.
                    </div>

                    <?php if($canManageUserPermissions && $editableMatrixUserCount > 0): ?>
                        <button class="btn-primary" type="submit">Save User Access</button>
                    <?php else: ?>
                        <button class="btn-ghost" type="button" disabled><?php echo e($canManageUserPermissions ? 'No Editable User' : 'Requires Role Permission'); ?></button>
                    <?php endif; ?>
                </div>
            </form>
        </div>

        </div>

        <aside class="right-stack">
            <div class="card form-panel">
                <div class="panel-head">
                    <h3 id="userFormTitle">Create User</h3>
                    <span class="muted">Dropdown role assignment</span>
                </div>

                <?php if($canManageUsers): ?>
                    <form
                        class="form-grid"
                        id="userForm"
                        data-frontend-form
                        data-action="<?php echo e(route('api.users.store')); ?>"
                        data-store-url="<?php echo e(route('api.users.store')); ?>"
                        data-success="User saved successfully."
                    >
                        <?php echo csrf_field(); ?>

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
                            <select class="role-select" name="role_ids[]" id="roleSelect" required>
                                <option value="">Select role</option>
                                <?php $__currentLoopData = $roles; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $role): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                    <?php
                                        $canAssign = isset($assignableLookup[(int) $role->id]);
                                    ?>
                                    <option
                                        value="<?php echo e($role->id); ?>"
                                        data-role-name="<?php echo e($role->name); ?>"
                                        data-role-level="<?php echo e($role->level); ?>"
                                        <?php echo e($canAssign ? '' : 'disabled'); ?>

                                    >
                                        <?php echo e($role->name); ?> — Level <?php echo e($role->level); ?>

                                        <?php echo e($canAssign ? '' : ' — Not assignable'); ?>

                                    </option>
                                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                            </select>
                            <div class="hint" id="rolePreview">Select one role for this user.</div>
                        </div>

                        <div class="hint-box">
                            <strong>Role rule</strong>
                            Super Admin can assign all active roles. Admin users can assign only the roles allowed by the hierarchy rule. The selected role provides the default backend access profile; the user access matrix can then allow or block permissions for that specific user.
                        </div>

                        <div class="form-actions">
                            <button type="button" class="btn-ghost" id="cancelUserBtn">Cancel</button>
                            <button type="submit" class="btn-primary">Save User</button>
                        </div>
                    </form>
                <?php else: ?>
                    <div class="hint-box">
                        <strong>View-only access</strong>
                        Your role can view this screen but cannot create users, change roles, or update permissions.
                    </div>
                <?php endif; ?>
            </div>

            <div class="card info-card role-help-card">
                <h3>User Role Flow</h3>

                <div class="role-help-row">
                    <span class="role-help-dot">1</span>
                    <div>
                        <strong>Create user</strong>
                        <p class="muted" style="margin:4px 0 0">Select one role from the dropdown. The old card/radio role selector is removed.</p>
                    </div>
                </div>

                <div class="role-help-row">
                    <span class="role-help-dot">2</span>
                    <div>
                        <strong>User appears in table</strong>
                        <p class="muted" style="margin:4px 0 0">After saving, the created user is listed in the user table and can be edited there.</p>
                    </div>
                </div>

                <div class="role-help-row">
                    <span class="role-help-dot">3</span>
                    <div>
                        <strong>User access matrix</strong>
                        <p class="muted" style="margin:4px 0 0">Use the Allow Access / Block Access matrix to control each created user separately.</p>
                    </div>
                </div>
            </div>
        </aside>
    </div>
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

<?php if($canManageUsers): ?>
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
    const roleSelect = document.getElementById('roleSelect');
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';

    const name = form.querySelector('[name="name"]');
    const email = form.querySelector('[name="email"]');
    const status = form.querySelector('[name="status"]');

    function showToast(message) {
        if (window.AccountingUI?.showToast) {
            window.AccountingUI.showToast(message);
            return;
        }

        alert(message);
    }

    function selectedRoleText(selectElement = roleSelect) {
        const option = selectElement?.selectedOptions?.[0];

        if (!option || !option.value) {
            return '';
        }

        const roleName = option.dataset.roleName || option.textContent.trim();
        const roleLevel = option.dataset.roleLevel || '';

        return roleLevel ? `${roleName} (L${roleLevel})` : roleName;
    }

    function updateRolePreview() {
        const selected = selectedRoleText(roleSelect);
        rolePreview.textContent = selected ? `Selected: ${selected}` : 'Select one role for this user.';
    }

    function clearRole() {
        roleSelect.value = '';
        updateRolePreview();
    }

    function setRole(roleIds) {
        const selectedIds = Array.isArray(roleIds) ? roleIds.map((id) => String(id)) : [];
        const firstSelectable = selectedIds.find((id) => {
            const option = Array.from(roleSelect.options).find((candidate) => candidate.value === id);
            return option && !option.disabled;
        });

        roleSelect.value = firstSelectable || '';
        updateRolePreview();
    }

    function resetForm() {
        form.reset();
        clearRole();
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
            setRole(JSON.parse(row.dataset.roleIds || '[]'));
        } catch (error) {
            clearRole();
        }

        form.scrollIntoView({ behavior: 'smooth', block: 'center' });
        showToast('User loaded for editing.');
    }

    form.addEventListener('submit', (event) => {
        if (!roleSelect.value) {
            event.preventDefault();
            event.stopImmediatePropagation();
            showToast('Please select one role.');
            roleSelect.focus();
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

    roleSelect.addEventListener('change', updateRolePreview);

    document.querySelectorAll('#usersTable .edit-btn').forEach((button) => {
        button.addEventListener('click', () => loadForEdit(button.closest('tr')));
    });

    document.querySelectorAll('[data-inline-role-select]').forEach((select) => {
        select.addEventListener('focus', () => {
            select.dataset.previousValue = select.value || select.dataset.originalRole || '';
        });

        select.addEventListener('change', async () => {
            const row = select.closest('tr');
            const selectedRoleId = select.value;
            const previousValue = select.dataset.previousValue || select.dataset.originalRole || '';

            if (!row || row.dataset.canManage !== '1' || !selectedRoleId) {
                select.value = previousValue;
                return;
            }

            if (selectedRoleId === previousValue) {
                return;
            }

            const selectedLabel = selectedRoleText(select);
            const confirmed = confirm(`Change role for ${row.dataset.name || 'this user'} to ${selectedLabel}?`);

            if (!confirmed) {
                select.value = previousValue;
                return;
            }

            select.disabled = true;

            const payload = new FormData();
            payload.append('_token', csrfToken);
            payload.append('_method', 'PUT');
            payload.append('name', row.dataset.name || '');
            payload.append('email', row.dataset.email || '');
            payload.append('status', row.dataset.status || 'Active');
            payload.append('role_ids[]', selectedRoleId);

            try {
                const response = await fetch(row.dataset.updateUrl, {
                    method: 'POST',
                    headers: {
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': csrfToken,
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    credentials: 'same-origin',
                    body: payload,
                });

                const result = await response.json().catch(() => ({}));

                if (!response.ok) {
                    throw new Error(result.message || 'Role could not be updated.');
                }

                showToast(result.message || 'User role updated successfully.');
                select.dataset.previousValue = selectedRoleId;
                select.dataset.originalRole = selectedRoleId;

                window.setTimeout(() => {
                    window.location.href = result.redirect || window.location.href;
                }, 650);
            } catch (error) {
                console.error(error);
                select.value = previousValue;
                showToast(error.message || 'Role could not be updated.');
            } finally {
                select.disabled = false;
            }
        });
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
<?php endif; ?>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH /Applications/XAMPP/xamppfiles/htdocs/laravel/project_work/resources/views/settings/users-roles.blade.php ENDPATH**/ ?>