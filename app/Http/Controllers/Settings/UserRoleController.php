<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use Throwable;
use App\Services\Setup\EntityDeleteService;
use App\Http\Controllers\Concerns\RespondsToDelete;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class UserRoleController extends Controller
{
    use RespondsToDelete;

    public function index(): View
    {
        $users = User::query()
            ->with('roles')
            ->orderBy('name')
            ->get();

        $roles = Role::query()
            ->where('status', 'Active')
            ->orderBy('name')
            ->get();

        return view('settings.users-roles', [
            'users' => $users,
            'roles' => $roles,
        ]);
    }

    public function storeUser(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8'],
            'role' => ['nullable', 'string', Rule::exists('roles', 'name')],
            'role_ids' => ['nullable', 'array'],
            'role_ids.*' => ['exists:roles,id'],
            'status' => ['required', Rule::in(['Active', 'Inactive'])],
        ]);

        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
            'status' => $data['status'],
        ]);

        $roleIds = $this->roleIds($data);
        $user->roles()->sync($roleIds);

        return response()->json([
            'success' => true,
            'message' => 'User saved successfully.',
            'data' => $user->load('roles'),
            'redirect' => route('settings.users-roles'),
        ], 201);
    }

    public function updateUser(Request $request, User $user): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'password' => ['nullable', 'string', 'min:8'],
            'role' => ['nullable', 'string', Rule::exists('roles', 'name')],
            'role_ids' => ['nullable', 'array'],
            'role_ids.*' => ['exists:roles,id'],
            'status' => ['required', Rule::in(['Active', 'Inactive'])],
        ]);

        $payload = [
            'name' => $data['name'],
            'email' => $data['email'],
            'status' => $data['status'],
        ];

        if (!empty($data['password'])) {
            $payload['password'] = Hash::make($data['password']);
        }

        $user->update($payload);
        $user->roles()->sync($this->roleIds($data));

        return response()->json([
            'success' => true,
            'message' => 'User updated successfully.',
            'data' => $user->load('roles'),
            'redirect' => route('settings.users-roles'),
        ]);
    }

    public function destroyUser(
        Request $request,
        User $user,
        EntityDeleteService $deleteService
    ): JsonResponse|RedirectResponse {
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

    private function roleIds(array $data): array
    {
        $roleIds = $data['role_ids'] ?? [];

        if (empty($roleIds) && !empty($data['role'])) {
            $roleIds = Role::where('name', $data['role'])->pluck('id')->all();
        }

        return $roleIds;
    }
}
