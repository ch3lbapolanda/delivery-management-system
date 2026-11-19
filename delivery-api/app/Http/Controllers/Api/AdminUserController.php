<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class AdminUserController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'string',
                'email',
                'max:255',
                'unique:users,email',
            ],
            'password' => ['required', 'string', 'min:8'],
            'role' => [
                'required',
                'string',
                Rule::in(['dispatcher', 'courier']),
            ],
        ]);

        $role = Role::where('slug', $validated['role'])->first();

        if (!$role) {
            return response()->json([
                'message' => 'Selected role is not available.',
            ], 422);
        }

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'role_id' => $role->id,
            'is_active' => true,
            'availability_status' => 'available',
        ]);

        $user->load('role');

        return response()->json([
            'message' => 'User created successfully.',
            'data' => $user,
        ], 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $user = User::with('role')->findOrFail($id);

        if ($user->role?->slug === 'admin') {
            return response()->json([
                'message' => 'Admin accounts cannot be edited here.',
            ], 403);
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'string',
                'email',
                'max:255',
                Rule::unique('users', 'email')->ignore($user->id),
            ],
            'role' => [
                'required',
                'string',
                Rule::in(['dispatcher', 'courier']),
            ],
        ]);

        $role = Role::where('slug', $validated['role'])->first();

        if (!$role) {
            return response()->json([
                'message' => 'Selected role is not available.',
            ], 422);
        }

        $user->update([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'role_id' => $role->id,
        ]);

        $user->load('role');

        return response()->json([
            'message' => 'User updated successfully.',
            'data' => $user,
        ]);
    }
  public function updateStatus(Request $request, int $id): JsonResponse
{
    $validated = $request->validate([
        'is_active' => ['required', 'boolean'],
    ]);

    $user = User::with('role')->findOrFail($id);

    if ($user->role?->slug === 'admin') {
        return response()->json([
            'message' => 'Admin accounts cannot be deactivated.',
        ], 403);
    }

    if (
        $request->user()->id === $user->id &&
        !$validated['is_active']
    ) {
        return response()->json([
            'message' => 'You cannot deactivate your own account.',
        ], 403);
    }

    $user->update([
        'is_active' => $validated['is_active'],
    ]);

    if (!$user->is_active) {
        $user->tokens()->delete();
    }

    return response()->json([
        'message' => $user->is_active
            ? 'User account activated successfully.'
            : 'User account deactivated successfully.',
        'data' => [
            'id' => $user->id,
            'name' => $user->name,
            'is_active' => $user->is_active,
        ],
    ]);
}
}

