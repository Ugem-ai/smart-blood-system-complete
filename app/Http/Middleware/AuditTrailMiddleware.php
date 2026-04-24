<?php

namespace App\Http\Middleware;

use App\Models\ActivityLog;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class AuditTrailMiddleware
{
    /**
     * Log authenticated API data access for audit trail compliance.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $user = $request->user();
        $path = '/'.$request->path();

        if ($user && ! Str::is(['api/admin/logs', 'api/admin/logs/*'], $request->path())) {
            ActivityLog::record($user->id, 'data.access', [
                'method' => $request->method(),
                'path' => $path,
                'status' => $response->getStatusCode() >= 400 ? 'warning' : 'success',
                'http_status' => $response->getStatusCode(),
                'ip' => $request->ip(),
                'user_agent' => (string) $request->userAgent(),
                'actor_role' => $user->role,
                'category' => 'data_access',
                'severity' => $response->getStatusCode() >= 400 ? 'medium' : 'low',
                'target_type' => 'route',
                'target_label' => $path,
            ]);
        }

        return $response;
    }
}
