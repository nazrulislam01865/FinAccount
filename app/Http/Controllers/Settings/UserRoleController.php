<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Concerns\RespondsToDelete;
use App\Http\Controllers\Controller;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Services\Setup\EntityDeleteService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;
use Throwable;

class UserRoleController extends Controller
{
    use RespondsToDelete;

    public function index(Request $request): View
    {
        $actor = $request->user();

        $users = User::query()
            ->with('roles.permissions')
            ->orderBy('name')
            ->get();

        $roles = Role::query()
            ->with('permissions')
            ->where('status', 'Active')
            ->orderBy('level')
            ->orderBy('name')
            ->get();

        $permissionLabels = collect(config('access.permissions', []));
        $permissionRows = $permissionLabels
            ->map(fn (string $label, string $name) => [
                'name' => $name,
                'label' => $label,
                'module' => $this->permissionModule($name),
            ])
            ->values();

        $rolePermissionMatrix = $roles->mapWithKeys(function (Role $role) {
            return [
                (int) $role->id => $role->permissions
                    ->pluck('name')
                    ->mapWithKeys(fn (string $name) => [$name => true])
                    ->all(),
            ];
        });

        $assignableRoleIds = $actor?->manageableRoleIds($roles) ?? [];

        return view('settings.users-roles', [
            'users' => $users,
            'roles' => $roles,
            'assignableRoleIds' => $assignableRoleIds,
            'permissionRows' => $permissionRows,
            'rolePermissionMatrix' => $rolePermissionMatrix,
            'canManageUsers' => $actor?->hasPermission('users.manage') ?? false,
            'canManageRolePermissions' => $actor?->hasPermission('roles.manage') ?? false,
        ]);
    }

    public function storeUser(Request $request): JsonResponse
    {
        $actor = $request->user();
        abort_unless($actor?->hasPermission('users.manage'), 403, 'You are not allowed to create users.');

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'confirmed', Password::min(8)],
            'role_ids' => ['required', 'array', 'min:1'],
            'role_ids.*' => ['integer', Rule::exists('roles', 'id')->where(fn ($query) => $query->where('status', 'Active'))],
            'status' => ['required', Rule::in(['Active', 'Inactive'])],
        ]);

        $roleIds = $this->authorizedRoleIds($actor, $data['role_ids']);

        $user = DB::transaction(function () use ($data, $roleIds) {
            $user = User::create([
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => Hash::make($data['password']),
                'status' => $data['status'],
            ]);

            $user->roles()->sync($roleIds);

            return $user;
        });

        return response()->json([
            'success' => true,
            'message' => 'User created successfully.',
            'data' => $user->load('roles'),
            'redirect' => route('settings.users-roles'),
        ], 201);
    }

    public function updateUser(Request $request, User $user): JsonResponse
    {
        $actor = $request->user();
        abort_unless($actor?->canManageUser($user), 403, 'You cannot update this user because of role hierarchy rules.');

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'password' => ['nullable', 'confirmed', Password::min(8)],
            'role_ids' => ['required', 'array', 'min:1'],
            'role_ids.*' => ['integer', Rule::exists('roles', 'id')->where(fn ($query) => $query->where('status', 'Active'))],
            'status' => ['required', Rule::in(['Active', 'Inactive'])],
        ]);

        $roleIds = $this->authorizedRoleIds($actor, $data['role_ids']);
        $this->guardLastSuperAdmin($user, $roleIds, $data['status']);

        DB::transaction(function () use ($user, $data, $roleIds) {
            $payload = [
                'name' => $data['name'],
                'email' => $data['email'],
                'status' => $data['status'],
            ];

            if (!empty($data['password'])) {
                $payload['password'] = Hash::make($data['password']);
            }

            $user->update($payload);
            $user->roles()->sync($roleIds);
        });

        return response()->json([
            'success' => true,
            'message' => 'User updated successfully.',
            'data' => $user->fresh('roles'),
            'redirect' => route('settings.users-roles'),
        ]);
    }

    public function destroyUser(
        Request $request,
        User $user,
        EntityDeleteService $deleteService
    ): JsonResponse|RedirectResponse {
        $actor = $request->user();
        abort_unless($actor?->canManageUser($user), 403, 'You cannot delete this user because of role hierarchy rules.');
        $this->guardLastSuperAdmin($user, [], 'Inactive', deleting: true);

        try {
            $deleteService->deleteUser($user->id);
        } catch (Throwable $exception) {
            return $this->deleteFailure(
                $request,
                'settings.users-roles',
                'This user could not be deleted. Please try again or check related records.',
                $exception
            );
        }

        return $this->deleteSuccess(
            $request,
            'settings.users-roles',
            'User deleted successfully.'
        );
    }

    public function updateRolePermissions(Request $request): JsonResponse
    {
        $actor = $request->user();
        abort_unless($actor?->hasPermission('roles.manage'), 403, 'You are not allowed to update role permission access.');

        $data = $request->validate([
            'permissions' => ['required', 'array'],
            'permissions.*' => ['array'],
        ]);

        $roles = Role::query()
            ->where('status', 'Active')
            ->with('permissions')
            ->get()
            ->keyBy(fn (Role $role) => (int) $role->id);

        $permissionIdsByName = Permission::query()->pluck('id', 'name');
        $allPermissionIds = $permissionIdsByName->values()->map(fn ($id) => (int) $id)->all();
        $configuredPermissionNames = array_keys(config('access.permissions', []));

        DB::transaction(function () use ($data, $roles, $permissionIdsByName, $allPermissionIds, $configuredPermissionNames) {
            foreach ($data['permissions'] as $roleId => $permissionPayload) {
                $role = $roles->get((int) $roleId);

                if (!$role) {
                    abort(422, 'One or more selected roles are invalid.');
                }

                if ($role->isSuperAdmin()) {
                    $role->permissions()->sync($allPermissionIds);
                    continue;
                }

                $selectedPermissionIds = collect($permissionPayload)
                    ->filter(fn ($value) => in_array((string) $value, ['1', 'true', 'on', 'yes'], true))
                    ->keys()
                    ->filter(fn ($permissionName) => in_array((string) $permissionName, $configuredPermissionNames, true))
                    ->map(fn ($permissionName) => $permissionIdsByName[$permissionName] ?? null)
                    ->filter()
                    ->map(fn ($id) => (int) $id)
                    ->unique()
                    ->values()
                    ->all();

                $role->permissions()->sync($selectedPermissionIds);
            }

            $superAdminRoles = $roles->filter(fn (Role $role) => $role->isSuperAdmin());

            foreach ($superAdminRoles as $role) {
                $role->permissions()->sync($allPermissionIds);
            }
        });

        return response()->json([
            'success' => true,
            'message' => 'Role permission matrix updated successfully.',
            'redirect' => route('settings.users-roles'),
        ]);
    }

    private function authorizedRoleIds(User $actor, array $requestedRoleIds): array
    {
        $requestedRoleIds = collect($requestedRoleIds)->map(fn ($id) => (int) $id)->unique()->values();
        $roles = Role::query()->whereIn('id', $requestedRoleIds)->where('status', 'Active')->get();

        if ($roles->count() !== $requestedRoleIds->count()) {
            abort(422, 'One or more selected roles are invalid.');
        }

        $blockedRole = $roles->first(fn (Role $role) => !$actor->canAssignRole($role));

        if ($blockedRole) {
            abort(403, 'You cannot assign the '.$blockedRole->name.' role because it is at your level or above.');
        }

        return $requestedRoleIds->all();
    }

    private function permissionModule(string $permission): string
    {
        $prefix = str($permission)->before('.')->headline()->toString();

        return match ($prefix) {
            'Cash Bank' => 'Cash / Bank',
            'Chart Of Accounts' => 'Chart of Accounts',
            'Due Management' => 'Due Management',
            'Advance Management' => 'Advance Management',
            'Ledger Mapping' => 'Accounting Rules',
            'Ledger Report' => 'Ledger Report',
            'Master Data' => 'Master Data',
            'Opening Balances' => 'Opening Balance',
            'Sales Invoices' => 'Sales Invoice',
            'Purchase Bills' => 'Purchase Bill',
            'Transaction Heads' => 'Transaction Heads',
            'Transactions' => 'Transaction Entry',
            'Voucher Numbering' => 'Voucher Numbering',
            default => $prefix,
        };
    }

    private function guardLastSuperAdmin(User $user, array $newRoleIds, string $newStatus, bool $deleting = false): void
    {
        if (!$user->isSuperAdmin()) {
            return;
        }

        $superAdminRoleId = Role::query()->where('name', 'Super Admin')->value('id');
        $keepsSuperAdminRole = !$deleting && in_array((int) $superAdminRoleId, array_map('intval', $newRoleIds), true);
        $keepsActiveStatus = !$deleting && $newStatus === 'Active';

        if ($keepsSuperAdminRole && $keepsActiveStatus) {
            return;
        }

        $otherActiveSuperAdmins = User::query()
            ->where('id', '!=', $user->id)
            ->where('status', 'Active')
            ->whereHas('roles', fn ($query) => $query->where('name', 'Super Admin')->where('status', 'Active'))
            ->exists();

        abort_unless($otherActiveSuperAdmins, 422, 'At least one active Super Admin must remain in the system.');
    }
}
