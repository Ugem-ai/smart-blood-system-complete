<?php

namespace App\Http\Middleware;

use App\Models\ActivityLog;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RoleMiddleware
{
    /**
     * Ensure the authenticated user has one of the allowed roles.
     */
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();
        $roleValue = is_object($user?->role) && property_exists($user->role, 'value')
            ? $user->role->value
            : (string) ($user?->role ?? '');

        if (! $user || ! in_array($roleValue, $roles, true)) {
            ActivityLog::record($user?->id, 'security.unauthorized-role-access', [
                'status' => 'blocked',
                'severity' => $user ? 'critical' : 'high',
                'category' => 'access',
                'actor_role' => $roleValue !== '' ? $roleValue : null,
                'required_roles' => $roles,
                'path' => '/'.$request->path(),
                'method' => $request->method(),
                'ip' => $request->ip(),
                'user_agent' => (string) $request->userAgent(),
                'target_type' => 'route',
                'target_label' => '/'.$request->path(),
                'reason' => 'role_mismatch',
            ]);

            abort(403, 'Unauthorized role access.');
        }

        return $next($request);
    }
}
