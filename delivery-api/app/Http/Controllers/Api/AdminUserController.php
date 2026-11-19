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
}

