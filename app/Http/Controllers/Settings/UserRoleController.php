<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Concerns\RespondsToDelete;
use App\Http\Controllers\Controller;
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

        $assignableRoleIds = $actor?->manageableRoleIds($roles) ?? [];

        return view('settings.users-roles', [
            'users' => $users,
            'roles' => $roles,
            'assignableRoleIds' => $assignableRoleIds,
            'accessMatrix' => config('access.matrix', []),
            'matrixColumns' => array_keys(config('access.roles', [])),
            'canManageUsers' => $actor?->hasPermission('users.manage') ?? false,
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
