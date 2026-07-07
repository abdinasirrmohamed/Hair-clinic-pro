<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\AuditLogService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    /**
     * POST /api/auth/login
     */
    public function login(Request $request): JsonResponse
    {
        $request->validate([
            'username' => 'required|string',
            'password' => 'required|string',
        ]);

        $user = User::where('username', $request->username)->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            return response()->json(['message' => 'Invalid username or password.'], 401);
        }

        if ($user->status !== 'Active') {
            return response()->json(['message' => 'Your account is inactive. Please contact an administrator.'], 403);
        }

        // Revoke previous tokens
        $user->tokens()->delete();

        $token = $user->createToken('auth-token')->plainTextToken;

        // Log activity manually since Auth::user() isn't set yet for AuditLogService
        \App\Models\AuditLog::create([
            'user_id' => $user->id,
            'user_name' => $user->full_name,
            'user_role' => $user->role,
            'action' => 'Logged in',
            'module_name' => 'Auth',
            'ip_address' => $request->ip(),
        ]);

        $redirect = match($user->role) {
            'Pharmacy User' => '/pharmacy/dashboard',
            'Inventory Officer' => '/inventory/medicines',
            default => '/dashboard',
        };

        return response()->json([
            'message' => 'Login successful.',
            'user' => [
                'id' => $user->id,
                'username' => $user->username,
                'full_name' => $user->full_name,
                'role' => $user->role,
                'status' => $user->status,
                'profile_photo_path' => $user->profile_photo_path,
                'profile_photo_url' => $user->profile_photo_url,
            ],
            'token' => $token,
            'redirect' => $redirect,
        ]);
    }

    /**
     * POST /api/auth/pharmacy-login
     */
    public function pharmacyLogin(Request $request): JsonResponse
    {
        $request->validate([
            'username' => 'required|string',
            'password' => 'required|string',
        ]);

        $user = User::where('username', $request->username)
            ->where('role', 'Pharmacy User')
            ->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            return response()->json(['message' => 'Invalid credentials or not a Pharmacy User.'], 401);
        }

        if ($user->status !== 'Active') {
            return response()->json(['message' => 'Your account is inactive.'], 403);
        }

        $user->tokens()->delete();
        $token = $user->createToken('pharmacy-token')->plainTextToken;

        \App\Models\AuditLog::create([
            'user_id' => $user->id,
            'user_name' => $user->full_name,
            'user_role' => $user->role,
            'action' => 'Pharmacy login',
            'module_name' => 'Auth',
            'ip_address' => $request->ip(),
        ]);

        return response()->json([
            'message' => 'Pharmacy login successful.',
            'user' => [
                'id' => $user->id,
                'username' => $user->username,
                'full_name' => $user->full_name,
                'role' => $user->role,
                'profile_photo_path' => $user->profile_photo_path,
                'profile_photo_url' => $user->profile_photo_url,
            ],
            'token' => $token,
            'redirect' => '/pharmacy/dashboard',
        ]);
    }

    /**
     * POST /api/auth/logout
     */
    public function logout(Request $request): JsonResponse
    {
        $user = $request->user();

        AuditLogService::log('Logged out', 'Auth');

        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Logged out successfully.']);
    }

    /**
     * GET /api/auth/me
     */
    public function me(Request $request): JsonResponse
    {
        $user = $request->user();
        return response()->json([
            'id' => $user->id,
            'username' => $user->username,
            'full_name' => $user->full_name,
            'role' => $user->role,
            'status' => $user->status,
            'profile_photo_path' => $user->profile_photo_path,
            'profile_photo_url' => $user->profile_photo_url,
            'created_at' => $user->created_at,
        ]);
    }
}
