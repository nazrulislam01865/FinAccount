<?php

namespace App\Http\Controllers\Accounting\System;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Concerns\RedirectsByAccountingAccess;
use App\Models\Access\AccountingRole;
use App\Models\User;
use App\Support\ActiveLoginSession;
use App\Support\AccountingRbac;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class UserManagementController extends Controller
{
    use RedirectsByAccountingAccess;
    public function index(Request $request): View
    {
        $companyId = (int) $request->user()->company_id;
        AccountingRbac::syncCompany($companyId);

        $canViewUsers = $request->user()->canAccounting('users.view');

        return view('system.users', [
            'users' => $canViewUsers
                ? User::query()->where('company_id', $companyId)->with('accountingRole')->orderBy('name')->get()
                : collect(),
            'addOnlyMode' => ! $canViewUsers,
            'roleOptions' => $this->assignableRoles($request->user()),
            'accountStatusOptions' => User::accountStatusOptions(),
            'canManageUsers' => $request->user()->canAccounting('users.manage'),
            'canAssignSuperAdmin' => $request->user()->isSystemAdmin(),
            'canChangePasswords' => $request->user()->isSystemAdmin(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $actor = $request->user();
        $roleIds = $this->assignableRoles($actor)->pluck('id')->map(fn ($id) => (int) $id)->all();

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'accounting_role_id' => ['required', 'integer', Rule::in($roleIds)],
            'account_status' => ['required', Rule::in(array_keys(User::accountStatusOptions()))],
        ]);

        $role = AccountingRole::query()->where('company_id', $actor->company_id)->findOrFail($validated['accounting_role_id']);

        $user = DB::transaction(function () use ($actor, $validated, $role): User {
            $user = User::query()->create([
                'company_id' => $actor->company_id,
                'accounting_role_id' => $role->id,
                'role' => $role->isSuperAdmin() ? User::ROLE_SYSTEM_ADMIN : User::ROLE_ACCOUNTING_USER,
                'account_status' => $validated['account_status'],
                'name' => $validated['name'],
                'email' => $validated['email'],
                'email_verified_at' => now(),
                'password' => $validated['password'],
            ]);
            AccountingRbac::syncUserPermissionsFromRole($user);
            return $user;
        });

        return $this->redirectAfterAccountingSave($request, 'users.view', 'system.users.index', "User {$user->name} created successfully.");
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $actor = $request->user();
        abort_unless((int) $user->company_id === (int) $actor->company_id, 404);
        abort_if($user->accountingRole?->isSuperAdmin() && ! $actor->isSystemAdmin(), 403, 'Only a Super Admin may update another Super Admin account.');

        $roleIds = $this->assignableRoles($actor, $user)->pluck('id')->map(fn ($id) => (int) $id)->all();
        $rules = [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'accounting_role_id' => ['required', 'integer', Rule::in($roleIds)],
            'account_status' => ['required', Rule::in(array_keys(User::accountStatusOptions()))],
            'password' => ['nullable', 'string', 'min:8', 'confirmed'],
        ];
        $validated = $request->validate($rules);

        if ((int) $actor->id === (int) $user->id
            && ((int) $validated['accounting_role_id'] !== (int) $user->accounting_role_id || $validated['account_status'] !== $user->accountStatusValue())) {
            throw ValidationException::withMessages(['accounting_role_id' => 'You cannot change your own role or active status.']);
        }

        $newRole = AccountingRole::query()->where('company_id', $actor->company_id)->findOrFail($validated['accounting_role_id']);
        if ($this->wouldRemoveLastActiveSuperAdmin($user, $newRole, $validated['account_status'])) {
            throw ValidationException::withMessages(['accounting_role_id' => 'At least one active Super Admin account must remain.']);
        }

        DB::transaction(function () use ($request, $actor, $user, $validated, $newRole): void {
            $passwordChanged = $actor->isSystemAdmin() && filled($validated['password'] ?? null);
            $roleChanged = (int) $user->accounting_role_id !== (int) $newRole->id;
            $statusChanged = $user->accountStatusValue() !== $validated['account_status'];

            $attributes = [
                'name' => $validated['name'],
                'email' => $validated['email'],
                'accounting_role_id' => $newRole->id,
                'role' => $newRole->isSuperAdmin() ? User::ROLE_SYSTEM_ADMIN : User::ROLE_ACCOUNTING_USER,
                'account_status' => $validated['account_status'],
            ];
            if ($passwordChanged) {
                $attributes['password'] = $validated['password'];
            }
            if ($passwordChanged || $validated['account_status'] !== User::ACCOUNT_STATUS_ACTIVE) {
                $attributes['remember_token'] = null;
            }
            $user->forceFill($attributes)->save();
            if ($roleChanged) {
                AccountingRbac::syncUserPermissionsFromRole($user);
            }
            if ($passwordChanged || ($statusChanged && $validated['account_status'] !== User::ACCOUNT_STATUS_ACTIVE)) {
                $this->revokeSessions($request, $actor, $user);
            }
        });

        return $this->redirectAfterAccountingSave($request, 'users.view', 'system.users.index', 'User details, role and account status updated successfully.');
    }

    private function assignableRoles(User $actor, ?User $editing = null)
    {
        $query = AccountingRole::query()->where('company_id', $actor->company_id)->where('is_active', true)->orderBy('sort_order')->orderBy('name');
        if (! $actor->isSystemAdmin()) {
            $query->where('slug', '!=', 'super_admin');
        } elseif ($editing?->accountingRole?->slug === 'super_admin') {
            // keep current super-admin role selectable while editing
        }
        return $query->get();
    }

    private function revokeSessions(Request $request, User $actor, User $user): void
    {
        if (Schema::hasTable('sessions')) {
            $query = DB::table('sessions')->where('user_id', $user->id);
            if ((int) $actor->id === (int) $user->id && $request->hasSession()) {
                $query->where('id', '!=', $request->session()->getId());
            }
            $query->delete();
        }

        if ((int) $actor->id === (int) $user->id && $request->hasSession()) {
            app(ActiveLoginSession::class)->claim($request, $user);
            return;
        }

        app(ActiveLoginSession::class)->clearForUser($user);
    }

    private function wouldRemoveLastActiveSuperAdmin(User $user, AccountingRole $newRole, string $newStatus): bool
    {
        $currently = $user->accountingRole?->isSuperAdmin() && $user->isAccountActive();
        $remaining = $newRole->isSuperAdmin() && $newStatus === User::ACCOUNT_STATUS_ACTIVE;
        if (! $currently || $remaining) {
            return false;
        }
        return User::query()
            ->where('company_id', $user->company_id)
            ->where('account_status', User::ACCOUNT_STATUS_ACTIVE)
            ->whereHas('accountingRole', fn ($q) => $q->where('slug', 'super_admin'))
            ->count() <= 1;
    }
}
