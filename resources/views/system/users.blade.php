@php
    $draftRows = \App\Support\VisibleFormDrafts::forBase('system-users');
@endphp

<x-layouts::accounting title="Users">
    <div class="hg-page-header">
        <div><h1>System Users</h1><p>Create company users, assign roles, update account status, and control access from the Role Matrix.</p></div>
        <span class="hg-badge">{{ $users->count() }} users{{ $draftRows->isNotEmpty() ? ' · '.$draftRows->count().' drafts' : '' }}</span>
    </div>

    @if($canManageUsers)
    <section class="hg-card hg-system-card">
        <div class="hg-section-head"><div><h2>Create User</h2><p>The selected role immediately controls this user’s module access.</p></div></div>
        <form method="POST" action="{{ route('system.users.store') }}" class="hg-form-grid" data-draft-form data-draft-key="system-users.create" data-draft-title="Create User">
            @csrf
            <div class="hg-field"><label>Name <span class="hg-required">*</span></label><input name="name" value="{{ old('name') }}" required maxlength="255"></div>
            <div class="hg-field"><label>Email <span class="hg-required">*</span></label><input name="email" type="email" value="{{ old('email') }}" required maxlength="255"></div>
            <div class="hg-field"><label>Role <span class="hg-required">*</span></label><select name="accounting_role_id" required><option value="">Select role</option>@foreach($roleOptions as $role)<option value="{{ $role->id }}" @selected((string)old('accounting_role_id') === (string)$role->id)>{{ $role->name }}</option>@endforeach</select></div>
            <div class="hg-field"><label>Account Status <span class="hg-required">*</span></label><select name="account_status" required>@foreach($accountStatusOptions as $value => $label)<option value="{{ $value }}" @selected(old('account_status', 'active') === $value)>{{ $label }}</option>@endforeach</select></div>
            <div class="hg-field"><label>Password <span class="hg-required">*</span></label><input name="password" type="password" required minlength="8" autocomplete="new-password"></div>
            <div class="hg-field"><label>Confirm Password <span class="hg-required">*</span></label><input name="password_confirmation" type="password" required minlength="8" autocomplete="new-password"></div>
            <div class="hg-field full"><x-accounting.form-actions submit-label="Create User" /></div>
        </form>
    </section>
    @endif

    <section class="hg-card hg-system-card">
        <div class="hg-section-head"><div><h2>User List</h2><p>Roles and account status are enforced on every protected request.</p></div></div>
        @if($addOnlyMode ?? false)<div class="hg-info">You may create users, but your role is not allowed to view the user list.</div><div class="hg-spacer"></div>@endif
        <div class="hg-table-wrap">
            <table class="hg-table">
                <thead><tr><th>User</th><th>Role</th><th>Status</th><th>Created</th>@if($canManageUsers)<th>Action</th>@endif</tr></thead>
                <tbody>
                @forelse($users as $user)
                    <tr>
                        <td><strong>{{ $user->name }}</strong><br><span class="hg-muted">{{ $user->email }}</span></td>
                        <td>{{ $user->accountingRole?->name ?? 'No Role' }}</td>
                        <td><span class="hg-badge {{ $user->isAccountActive() ? 'sales' : 'payment' }}">{{ $user->accountStatusLabel() }}</span></td>
                        <td>{{ $user->created_at?->format('d M Y h:i A') }}</td>
                        @if($canManageUsers)
                        <td>
                            @if($canAssignSuperAdmin || ! $user->accountingRole?->isSuperAdmin())
                                @php
                                    $userEditPayload = [
                                        'id' => $user->id,
                                        'name' => $user->name,
                                        'email' => $user->email,
                                        'role' => $user->accounting_role_id,
                                        'status' => $user->accountStatusValue(),
                                        'self' => auth()->id() === $user->id,
                                        'url' => route('system.users.update', $user),
                                    ];
                                @endphp
                                <button type="button" class="hg-btn hg-btn-small" data-draft-edit-key="system-users.edit.{{ $user->id }}" data-user-edit='@json($userEditPayload)'>Edit</button>
                            @else
                                <span class="hg-muted">Protected</span>
                            @endif
                        </td>
                        @endif
                    </tr>
                @empty
                    @if($draftRows->isEmpty())<tr><td colspan="5" class="hg-empty">No users found.</td></tr>@endif
                @endforelse

                @foreach($draftRows as $draft)
                    @php $fields = \App\Support\VisibleFormDrafts::fields($draft); $isEditDraft = \App\Support\VisibleFormDrafts::isEdit($draft); @endphp
                    <tr class="hg-table-draft-row">
                        <td><strong>{{ $fields['name'] ?? 'Draft User' }}</strong><br><span class="hg-muted">{{ $fields['email'] ?? 'No email entered' }} · {{ $isEditDraft ? 'Unsaved edit' : 'Unsaved new user' }}</span></td>
                        <td>{{ filled($fields['accounting_role_id'] ?? null) ? 'Role ID #'.$fields['accounting_role_id'] : 'No Role selected' }}</td>
                        <td><span class="hg-badge draft">Draft</span></td>
                        <td>{{ $draft->updated_at?->format('d M Y h:i A') }}</td>
                        @if($canManageUsers)
                        <td><div class="hg-actions">
                            @if($isEditDraft)<button type="button" class="hg-btn hg-btn-small" data-draft-open-existing="{{ $draft->draft_key }}">Continue</button>@else<a class="hg-btn hg-btn-small" href="{{ route('system.users.index') }}">Continue</a>@endif
                            <form method="POST" action="{{ route('accounting.form-drafts.destroy', $draft->draft_key) }}">@csrf @method('DELETE')<button class="hg-btn hg-btn-small hg-btn-danger" type="submit">Discard</button></form>
                        </div></td>
                        @endif
                    </tr>
                @endforeach

                </tbody>
            </table>
        </div>
    </section>

    @if($canManageUsers)
    <div class="hg-modal" id="userEditModal" aria-hidden="true">
        <div class="hg-modal-card hg-user-modal-card">
            <div class="hg-modal-head"><div><h3>Edit User</h3><p id="editUserSubtitle">Update details, role and account status.</p></div><button type="button" class="hg-modal-close" data-user-edit-close>×</button></div>
            <form id="editUserForm" method="POST" action="" data-draft-form data-draft-defer data-draft-key-base="system-users" data-draft-key="system-users.edit" data-draft-title="Edit User">
                @csrf @method('PUT')
                <div class="hg-form-grid">
                    <div class="hg-field"><label>Name <span class="hg-required">*</span></label><input id="editUserName" name="name" required></div>
                    <div class="hg-field"><label>Email <span class="hg-required">*</span></label><input id="editUserEmail" name="email" type="email" required></div>
                    <div class="hg-field"><label>Role <span class="hg-required">*</span></label><select id="editUserRole" name="accounting_role_id" required>@foreach($roleOptions as $role)<option value="{{ $role->id }}">{{ $role->name }}</option>@endforeach</select></div>
                    <div class="hg-field"><label>Status <span class="hg-required">*</span></label><select id="editUserStatus" name="account_status" required>@foreach($accountStatusOptions as $value => $label)<option value="{{ $value }}">{{ $label }}</option>@endforeach</select></div>
                    @if($canChangePasswords)
                    <div class="hg-field"><label>New Password</label><input name="password" type="password" minlength="8" autocomplete="new-password"></div>
                    <div class="hg-field"><label>Confirm New Password</label><input name="password_confirmation" type="password" minlength="8" autocomplete="new-password"></div>
                    @endif
                    <div class="hg-field full hg-info" id="editSelfNotice" hidden>You cannot change your own role or active status.</div>
                    <div class="hg-field full"><x-accounting.form-actions submit-label="Save User Changes"><button type="button" class="hg-btn" data-user-edit-close>Cancel</button></x-accounting.form-actions></div>
                </div>
            </form>
        </div>
    </div>
    @endif

    @push('scripts')
    <script>
    (() => {
        const modal = document.getElementById('userEditModal');
        const form = document.getElementById('editUserForm');
        if (!modal || !form) return;
        document.querySelectorAll('[data-user-edit]').forEach(button => button.addEventListener('click', () => {
            const data = JSON.parse(button.dataset.userEdit || '{}');
            form.querySelectorAll('[data-self-hidden]').forEach(el => el.remove());
            form.reset();
            form.action = data.url || '';
            document.getElementById('editUserName').value = data.name || '';
            document.getElementById('editUserEmail').value = data.email || '';
            const role = document.getElementById('editUserRole');
            const status = document.getElementById('editUserStatus');
            role.value = data.role || '';
            status.value = data.status || 'active';
            role.disabled = !!data.self;
            status.disabled = !!data.self;
            document.getElementById('editSelfNotice').hidden = !data.self;
            if (data.self) {
                const roleHidden = document.createElement('input'); roleHidden.type='hidden'; roleHidden.name='accounting_role_id'; roleHidden.value=data.role; roleHidden.dataset.selfHidden='1'; form.appendChild(roleHidden);
                const statusHidden = document.createElement('input'); statusHidden.type='hidden'; statusHidden.name='account_status'; statusHidden.value=data.status; statusHidden.dataset.selfHidden='1'; form.appendChild(statusHidden);
            }
            document.getElementById('editUserSubtitle').textContent = `Editing ${data.name || 'user'} (${data.email || ''})`;
            const draftKey = `system-users.edit.${data.id}`;
            form.dataset.draftKey = draftKey;
            modal.classList.add('show'); modal.setAttribute('aria-hidden','false');
            form.dispatchEvent(new CustomEvent('hisebghor:draft-context', {
                detail: { key: draftKey, title: `Edit User: ${data.name || data.email || data.id}` },
            }));
        }));
        const close = () => { modal.classList.remove('show'); modal.setAttribute('aria-hidden','true'); form.querySelectorAll('[data-self-hidden]').forEach(el=>el.remove()); document.getElementById('editUserRole').disabled=false; document.getElementById('editUserStatus').disabled=false; };
        modal.querySelectorAll('[data-user-edit-close]').forEach(button => button.addEventListener('click', close));
        modal.addEventListener('click', event => { if (event.target === modal) close(); });
    })();
    </script>
    @endpush
</x-layouts::accounting>
