<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class UserRoleController extends Controller
{
    public function storeUser(Request $request)
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

        $roleIds = $data['role_ids'] ?? [];
        if (empty($roleIds) && !empty($data['role'])) {
            $roleIds = Role::where('name', $data['role'])->pluck('id')->all();
        }
        $user->roles()->sync($roleIds);

        return response()->json([
            'success' => true,
            'message' => 'User saved successfully.',
            'data' => $user->load('roles'),
        ], 201);
    }
}
