<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\AuditLogService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class UserController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $users = User::paginate(15);
        return response()->json($users);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'username' => 'required|string|unique:users',
            'full_name' => 'required|string',
            'role' => ['required', Rule::in(['Administrator', 'Receptionist', 'Doctor', 'Inventory Officer', 'Pharmacy User'])],
            'password' => 'required|string|min:8|regex:/[a-z]/|regex:/[A-Z]/|regex:/[0-9]/|regex:/[@$!%*#?&]/',
            'status' => ['nullable', Rule::in(['Active', 'Inactive'])],
        ]);

        $validated['password'] = Hash::make($validated['password']);
        $validated['status'] = $validated['status'] ?? 'Active';

        $user = User::create($validated);

        AuditLogService::log('Created user', 'Users', $user->id);

        return response()->json($user, 201);
    }

    public function show(User $user): JsonResponse
    {
        return response()->json($user);
    }

    public function update(Request $request, User $user): JsonResponse
    {
        $validated = $request->validate([
            'username' => ['string', Rule::unique('users')->ignore($user->id)],
            'full_name' => 'string',
            'role' => [Rule::in(['Administrator', 'Receptionist', 'Doctor', 'Inventory Officer', 'Pharmacy User'])],
            'password' => 'nullable|string|min:8|regex:/[a-z]/|regex:/[A-Z]/|regex:/[0-9]/|regex:/[@$!%*#?&]/',
            'status' => [Rule::in(['Active', 'Inactive'])],
        ]);

        if (isset($validated['password'])) {
            $validated['password'] = Hash::make($validated['password']);
        } else {
            unset($validated['password']);
        }

        $user->update($validated);

        AuditLogService::log('Updated user', 'Users', $user->id);

        return response()->json($user);
    }

    public function destroy(User $user): JsonResponse
    {
        $user->delete();
        AuditLogService::log('Deleted user', 'Users', $user->id);
        return response()->json(null, 204);
    }

    public function profile(Request $request): JsonResponse
    {
        return response()->json($request->user());
    }

    public function updateProfile(Request $request): JsonResponse
    {
        $user = $request->user();
        
        $validated = $request->validate([
            'full_name' => 'required|string',
            'old_password' => 'nullable|string',
            'password' => 'nullable|string|min:8|regex:/[a-z]/|regex:/[A-Z]/|regex:/[0-9]/|regex:/[@$!%*#?&]/',
            'profile_photo' => 'nullable|image|mimes:jpeg,png,jpg,webp|max:3072',
        ]);

        if (isset($validated['password'])) {
            if (!Hash::check($validated['old_password'], $user->password)) {
                return response()->json(['message' => 'Incorrect old password.'], 400);
            }
            $user->password = Hash::make($validated['password']);
        }

        if ($request->hasFile('profile_photo')) {
            if ($user->profile_photo_path) {
                Storage::disk('public')->delete($user->profile_photo_path);
            }

            $user->profile_photo_path = $request->file('profile_photo')->store('profile-photos', 'public');
        }

        $user->full_name = $validated['full_name'];
        $user->save();

        AuditLogService::log('Updated profile', 'Users', $user->id);

        return response()->json($user->fresh());
    }
}
