<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class CheckRole
{
    public function handle(Request $request, Closure $next, string ...$roles): mixed
    {
        $user = $request->user();

        if (!$user || !in_array($user->role, $roles, true)) {
            return response()->json([
                'message' => 'Access denied. Your role does not have permission for this action.',
                'required_roles' => $roles,
                'your_role' => $user?->role,
            ], 403);
        }

        return $next($request);
    }
}
