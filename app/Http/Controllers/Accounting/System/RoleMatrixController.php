<?php

namespace App\Http\Controllers\Accounting\System;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Concerns\RedirectsByAccountingAccess;
use App\Models\Access\AccountingPermission;
use App\Models\Access\AccountingRole;
use App\Models\User;
use App\Support\AccountingRbac;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class RoleMatrixController extends Controller
{
    use RedirectsByAccountingAccess;
    public function index(Request $request): View
    {
        $companyId = (int) $request->user()->company_id;
        AccountingRbac::syncCompany($companyId);

        $roles = AccountingRole::query()
            ->where('company_id', $companyId)
            ->where('is_active', true)
            ->withCount('users')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        $permissions = AccountingPermission::query()
            ->where('key', '!=', 'settings.manage')
            ->orderBy('sort_order')
            ->get();
        $matrix = DB::table('accounting_role_permissions')
            ->whereIn('role_id', $roles->pluck('id'))
            ->join('accounting_permissions', 'accounting_permissions.id', '=', 'accounting_role_permissions.permission_id')
            ->get(['accounting_role_permissions.role_id', 'accounting_permissions.key', 'accounting_role_permissions.allowed'])
            ->groupBy('role_id')
            ->map(fn ($rows) => $rows->pluck('allowed', 'key')->map(fn ($value) => (bool) $value)->all())
            ->all();

        return view('system.role-matrix', [
            'roles' => $roles,
            'permissions' => $permissions,
            'permissionMatrix' => $matrix,
            'canManageRoleMatrix' => $request->user()->canAccounting('role_matrix.manage'),
            'canManageDeletePermission' => $request->user()->isSystemAdmin(),
        ]);
    }

    public function storeRole(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:500'],
        ]);
        $companyId = (int) $request->user()->company_id;

        if (AccountingRole::query()->where('company_id', $companyId)->whereRaw('LOWER(name) = ?', [strtolower($validated['name'])])->exists()) {
            throw ValidationException::withMessages(['name' => 'A role with this name already exists for your company.']);
        }

        DB::transaction(function () use ($validated, $companyId): void {
            $role = AccountingRole::query()->create([
                'company_id' => $companyId,
                'name' => $validated['name'],
                'slug' => AccountingRbac::uniqueRoleSlug($companyId, $validated['name']),
                'description' => $validated['description'] ?? null,
                'sort_order' => ((int) AccountingRole::query()->where('company_id', $companyId)->max('sort_order')) + 10,
                'is_system' => false,
                'is_active' => true,
            ]);

            $now = now();
            AccountingPermission::query()->pluck('id')->each(function ($permissionId) use ($role, $now): void {
                DB::table('accounting_role_permissions')->insert([
                    'role_id' => $role->id,
                    'permission_id' => $permissionId,
                    'allowed' => false,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            });
        });

        return $this->redirectAfterAccountingSave($request, 'role_matrix.view', 'system.role-matrix.index', 'Role created successfully. Select its permissions and save the matrix.');
    }

    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'permissions' => ['nullable', 'array'],
            'permissions.*' => ['nullable', 'array'],
            'permissions.*.*' => ['string'],
        ]);

        $companyId = (int) $request->user()->company_id;
        $input = $validated['permissions'] ?? [];
        $actorCanManageDelete = $request->user()->isSystemAdmin();

        DB::transaction(function () use ($companyId, $input, $actorCanManageDelete): void {
            $permissions = AccountingPermission::query()->orderBy('sort_order')->get();
            $validKeys = $permissions->pluck('key')->all();
            $roles = AccountingRole::query()->where('company_id', $companyId)->where('is_active', true)->get();
            $now = now();

            foreach ($roles as $role) {
                $allowedKeys = collect($input[$role->id] ?? [])->filter(fn ($key) => in_array($key, $validKeys, true))->values()->all();
                $existing = DB::table('accounting_role_permissions')->where('role_id', $role->id)->pluck('allowed', 'permission_id');

                foreach ($permissions as $permission) {
                    if ($permission->key === 'settings.manage') {
                        $allowed = $role->isSuperAdmin();
                    } elseif ($permission->key === AccountingRbac::DELETE_PERMISSION_KEY) {
                        $allowed = $role->isSuperAdmin()
                            || ($actorCanManageDelete
                                ? in_array($permission->key, $allowedKeys, true)
                                : (bool) ($existing[$permission->id] ?? false));
                    } else {
                        $allowed = $role->isSuperAdmin() || in_array($permission->key, $allowedKeys, true);
                    }

                    DB::table('accounting_role_permissions')->updateOrInsert(
                        ['role_id' => $role->id, 'permission_id' => $permission->id],
                        ['allowed' => $allowed, 'created_at' => $now, 'updated_at' => $now],
                    );
                }

                User::query()->where('company_id', $companyId)->where('accounting_role_id', $role->id)->get()
                    ->each(fn (User $user) => AccountingRbac::syncUserPermissionsFromRole($user));
            }
        });

        return $this->redirectAfterAccountingSave($request, 'role_matrix.view', 'system.role-matrix.index', 'Role permissions updated successfully. Assigned users now use the saved access.');
    }
}
