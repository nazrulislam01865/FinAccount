@php
    $roleDrafts = \App\Support\VisibleFormDrafts::forBase('roles');
@endphp

<x-layouts::accounting title="Role Matrix">
    <div class="hg-page-header">
        <div>
            <h1>Role Based Access Matrix</h1>
            <p>Create company roles and choose which HisebGhor modules each role can view or manage. Users receive access from the role assigned on the Users page.</p>
        </div>
        <span class="hg-badge">Roles control user access</span>
    </div>

    <div class="hg-role-overview-grid">
        @foreach($roles as $role)
            <article class="hg-role-overview-card {{ $role->isSuperAdmin() ? 'super' : '' }}">
                <span class="hg-role-icon">{{ $role->isSuperAdmin() ? '🛡️' : '👥' }}</span>
                <div>
                    <strong>{{ $role->name }}</strong>
                    <p>{{ $role->description ?: 'Custom company role.' }}</p>
                    <small>{{ $role->users_count }} assigned user{{ $role->users_count === 1 ? '' : 's' }} · {{ $role->is_system ? 'System' : 'Custom' }}</small>
                </div>
            </article>
        @endforeach
        @foreach($roleDrafts as $draft)
            @php $fields = \App\Support\VisibleFormDrafts::fields($draft); @endphp
            <article class="hg-role-overview-card hg-table-draft-row">
                <span class="hg-role-icon">📝</span>
                <div>
                    <strong>{{ $fields['name'] ?? 'Draft Role' }}</strong>
                    <p>{{ $fields['description'] ?? 'Unsaved role draft.' }}</p>
                    <small>Draft · {{ $draft->updated_at?->diffForHumans() }}</small>
                    <div class="hg-actions" style="margin-top:8px">
                        <a class="hg-btn hg-btn-small" href="{{ route('system.role-matrix.index') }}">Continue</a>
                        <form method="POST" action="{{ route('accounting.form-drafts.destroy', $draft->draft_key) }}">@csrf @method('DELETE')<button class="hg-btn hg-btn-small hg-btn-danger" type="submit">Discard</button></form>
                    </div>
                </div>
            </article>
        @endforeach
    </div>

    <section class="hg-card hg-system-card">
        <div class="hg-section-head">
            <div><h2>Create New Role</h2><p>Add a role, then select its permissions in the matrix.</p></div>
            <span class="hg-badge {{ $canManageRoleMatrix ? 'sales' : 'liability' }}">{{ $canManageRoleMatrix ? 'Role management enabled' : 'View only' }}</span>
        </div>
        @if($canManageRoleMatrix)
            <form method="POST" action="{{ route('system.role-matrix.roles.store') }}" class="hg-form-grid hg-role-create-grid" data-draft-form data-draft-key="roles.create" data-draft-title="Create Role">
                @csrf
                <div class="hg-field"><label for="role-name">Role Name <span class="hg-required">*</span></label><input id="role-name" name="name" value="{{ old('name') }}" required maxlength="100"></div>
                <div class="hg-field"><label for="role-description">Description</label><input id="role-description" name="description" value="{{ old('description') }}" maxlength="500"></div>
                <div class="hg-field full"><x-accounting.form-actions submit-label="Create Role" /></div>
            </form>
        @else
            <div class="hg-info">You can view role permissions, but you cannot create or update roles.</div>
        @endif
    </section>

    <form method="POST" action="{{ route('system.role-matrix.update') }}" data-draft-form data-draft-key="role-matrix" data-draft-title="Role Permission Matrix">
        @csrf
        <section class="hg-card hg-system-card">
            <div class="hg-section-head">
                <div>
                    <h2>Permission Matrix</h2>
                    <p>View and Manage are independent. Delete Records remains a separate protected permission and continues to use the existing safe-delete workflow.</p>
                </div>
                @if($canManageRoleMatrix)<x-accounting.form-actions submit-label="Save Role Matrix" />@endif
            </div>

            <div class="hg-info hg-role-note">Super Admin is protected and always has full access. Branding Settings stays Super Admin only. Only a Super Admin may grant or revoke Delete Records for other roles.</div>

            <div class="hg-table-wrap hg-role-table-wrap">
                <table class="hg-table hg-role-matrix-table">
                    <thead><tr><th>Permission</th><th>Action</th>@foreach($roles as $role)<th>{{ $role->name }}</th>@endforeach</tr></thead>
                    <tbody>
                    @foreach($permissions->groupBy('module') as $module => $modulePermissions)
                        <tr class="hg-role-module-row"><td colspan="{{ 2 + $roles->count() }}">{{ $module }}</td></tr>
                        @foreach($modulePermissions as $permission)
                            <tr>
                                <td class="hg-role-permission-copy"><strong>{{ $permission->label }}</strong><span>{{ $permission->description }}</span><code>{{ $permission->key }}</code></td>
                                <td><span class="hg-badge {{ $permission->action === 'Delete' ? 'payment' : ($permission->action === 'Manage' ? 'liability' : '') }}">{{ $permission->action }}</span></td>
                                @foreach($roles as $role)
                                    @php
                                        $isDelete = $permission->key === \App\Support\AccountingRbac::DELETE_PERMISSION_KEY;
                                        $checked = $role->isSuperAdmin() || (bool)($permissionMatrix[$role->id][$permission->key] ?? false);
                                        $disabled = !$canManageRoleMatrix || $role->isSuperAdmin() || ($isDelete && !$canManageDeletePermission) || ($permission->key === 'settings.manage');
                                    @endphp
                                    <td class="hg-role-check-cell">
                                        <label class="hg-role-check {{ $checked ? 'checked' : '' }} {{ $disabled ? 'disabled' : '' }}">
                                            <input type="checkbox" name="permissions[{{ $role->id }}][]" value="{{ $permission->key }}" @checked($checked) @disabled($disabled)>
                                            <span>{{ $checked ? '✓' : '' }}</span>
                                        </label>
                                    </td>
                                @endforeach
                            </tr>
                        @endforeach
                    @endforeach
                    </tbody>
                </table>
            </div>
        </section>
    </form>

    @push('scripts')
    <script>
    (() => {
        document.querySelectorAll('.hg-role-check input[type="checkbox"]').forEach((input) => {
            const label = input.closest('.hg-role-check');
            const marker = label?.querySelector('span');
            const refresh = () => {
                label?.classList.toggle('checked', input.checked);
                if (marker) marker.textContent = input.checked ? '✓' : '';
            };
            input.addEventListener('change', refresh);
            refresh();
        });
    })();
    </script>
    @endpush
</x-layouts::accounting>
