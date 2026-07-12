<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class CheckModuleAccess
{
    public function handle(Request $request, Closure $next, string $module): mixed
    {
        $user = $request->user();

        if (!$user) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        $allowedModules = method_exists($user, 'effectiveModulePermissions')
            ? $user->effectiveModulePermissions()
            : (config('roles.module_permissions')[$user->role] ?? []);

        if (!in_array($module, $allowedModules, true)) {
            return response()->json([
                'message' => 'Access denied. You do not have permission to access this module.',
                'module' => $module,
                'your_role' => $user->role,
            ], 403);
        }

        return $next($request);
    }
}
